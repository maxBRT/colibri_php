<?php

use App\Ai\Agents\MetadataDescriptionAgent;
use App\Models\Post;
use App\Services\EnrichmentService;
use Carbon\CarbonInterval;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Laravel\Ai\Exceptions\RateLimitedException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('ai.default', 'anthropic');
    config()->set('ai.providers.anthropic.model', 'claude-haiku-4-5-20251001');
    config()->set('ai.providers.anthropic.retries', 3);
    config()->set('ai.providers.anthropic.retry_sleep_ms', 0);
});

test('metadata agent uses anthropic provider with web fetch tool', function () {
    $agent = new MetadataDescriptionAgent;

    expect($agent->provider())->toBe('anthropic')
        ->and($agent->model())->toBe('claude-haiku-4-5-20251001')
        ->and($agent->tools())->toHaveCount(1);
});

test('it returns summary string when anthropic responds successfully', function () {
    MetadataDescriptionAgent::fake(['This is a generated summary.']);

    $post = Post::make([
        'title' => 'Test post',
        'link' => 'https://example.test/posts/1',
    ]);

    $summary = app(EnrichmentService::class)->generateSummary($post);

    expect($summary)->toBe('This is a generated summary.');
});

test('it sends expected payload to anthropic', function () {
    MetadataDescriptionAgent::fake(['Summary payload check.']);

    $post = Post::make([
        'title' => 'Payload post',
        'link' => 'https://example.test/posts/payload',
    ]);

    app(EnrichmentService::class)->generateSummary($post);

    MetadataDescriptionAgent::assertPrompted(function ($prompt) {
        return $prompt->contains('Payload post')
            && $prompt->contains('https://example.test/posts/payload');
    });
});

test('it retries and returns null on repeated network exception', function () {
    $attempts = 0;

    MetadataDescriptionAgent::fake(function () use (&$attempts) {
        $attempts++;

        throw new RuntimeException('Anthropic unavailable');
    });

    $post = Post::make([
        'title' => 'Failure post',
        'link' => 'https://example.test/posts/failure',
    ]);

    $summary = app(EnrichmentService::class)->generateSummary($post);

    expect($summary)->toBeNull()
        ->and($attempts)->toBe(3);
});

test('it retries on api failure and returns summary when later attempt succeeds', function () {
    $attempts = 0;

    MetadataDescriptionAgent::fake(function () use (&$attempts) {
        $attempts++;

        return match ($attempts) {
            1, 2 => throw new RuntimeException('Rate limited'),
            default => 'Recovered after retry',
        };
    });

    $post = Post::make([
        'title' => 'Rate limited post',
        'link' => 'https://example.test/posts/rate-limited',
    ]);

    $summary = app(EnrichmentService::class)->generateSummary($post);

    expect($summary)->toBe('Recovered after retry')
        ->and($attempts)->toBe(3);
});

test('it returns null after retries are exhausted on api failure', function () {
    $attempts = 0;

    MetadataDescriptionAgent::fake(function () use (&$attempts) {
        $attempts++;

        throw new RuntimeException('Rate limited');
    });

    $post = Post::make([
        'title' => 'Rate limited exhausted post',
        'link' => 'https://example.test/posts/rate-limited-exhausted',
    ]);

    $summary = app(EnrichmentService::class)->generateSummary($post);

    expect($summary)->toBeNull()
        ->and($attempts)->toBe(3);
});

test('it returns null when model returns no description sentinel', function () {
    MetadataDescriptionAgent::fake(['No description available.']);

    $post = Post::make([
        'title' => 'Malformed payload post',
        'link' => 'https://example.test/posts/malformed',
    ]);

    $summary = app(EnrichmentService::class)->generateSummary($post);

    expect($summary)->toBeNull();
});

test('it returns null on empty or whitespace summary', function () {
    MetadataDescriptionAgent::fake(['   ']);

    $post = Post::make([
        'title' => 'Whitespace summary post',
        'link' => 'https://example.test/posts/whitespace',
    ]);

    $summary = app(EnrichmentService::class)->generateSummary($post);

    expect($summary)->toBeNull();
});

test('it logs success when anthropic responds with a usable summary', function () {
    Log::partialMock()
        ->shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'LLM enrichment succeeded.'
                && $context['post_title'] === 'Logged success post'
                && $context['post_link'] === 'https://example.test/posts/logged-success'
                && $context['attempt'] === 1
                && $context['summary_length'] === 28;
        });

    MetadataDescriptionAgent::fake(['This is a generated summary.']);

    $post = Post::make([
        'title' => 'Logged success post',
        'link' => 'https://example.test/posts/logged-success',
    ]);

    app(EnrichmentService::class)->generateSummary($post);
});

test('it logs warning when anthropic returns no usable description', function () {
    Log::partialMock()
        ->shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'LLM enrichment returned no usable description.'
                && $context['post_title'] === 'Logged empty post'
                && $context['response'] === 'No description available.';
        });

    MetadataDescriptionAgent::fake(['No description available.']);

    $post = Post::make([
        'title' => 'Logged empty post',
        'link' => 'https://example.test/posts/logged-empty',
    ]);

    app(EnrichmentService::class)->generateSummary($post);
});

test('it logs retry attempts and final failure when all retries are exhausted', function () {
    Exceptions::fake();

    Log::partialMock()
        ->shouldReceive('warning')
        ->times(3)
        ->withArgs(function (string $message, array $context): bool {
            if ($message === 'LLM enrichment attempt failed, retrying.') {
                return $context['error'] === 'Anthropic unavailable'
                    && $context['exception'] === RuntimeException::class;
            }

            return $message === 'LLM enrichment failed after all retries.'
                && $context['attempts'] === 3
                && $context['error'] === 'Anthropic unavailable'
                && $context['exception'] === RuntimeException::class;
        });

    MetadataDescriptionAgent::fake(function () {
        throw new RuntimeException('Anthropic unavailable');
    });

    $post = Post::make([
        'title' => 'Logged failure post',
        'link' => 'https://example.test/posts/logged-failure',
    ]);

    app(EnrichmentService::class)->generateSummary($post);

    Exceptions::assertReported(RuntimeException::class);
});

test('it does not report expected anthropic rate limit failures', function () {
    Exceptions::fake();

    MetadataDescriptionAgent::fake(function () {
        throw RateLimitedException::forProvider('anthropic');
    });

    $post = Post::make([
        'title' => 'Rate limited post',
        'link' => 'https://example.test/posts/rate-limited',
    ]);

    app(EnrichmentService::class)->generateSummary($post);

    Exceptions::assertNothingReported();
});

test('it waits a full minute when anthropic input token rate limit is hit', function () {
    Sleep::fake();

    $attempts = 0;

    MetadataDescriptionAgent::fake(function () use (&$attempts) {
        $attempts++;

        $response = new Response(new PsrResponse(
            429,
            [],
            '{"type":"error","error":{"type":"rate_limit_error","message":"This request would exceed your organization\'s rate limit of 50,000 input tokens per minute"}}'
        ));

        throw RateLimitedException::forProvider(
            'anthropic',
            0,
            new RequestException($response),
        );
    });

    config()->set('ai.providers.anthropic.retries', 2);
    config()->set('ai.providers.anthropic.retry_sleep_ms', 0);

    $post = Post::make([
        'title' => 'Token rate limit post',
        'link' => 'https://example.test/posts/token-rate-limit',
    ]);

    app(EnrichmentService::class)->generateSummary($post);

    expect($attempts)->toBe(2);

    Sleep::assertSlept(fn (CarbonInterval $duration) => $duration->totalMilliseconds >= 60_000, times: 1);
});

test('it honors anthropic retry-after hints when rate limited', function () {
    $attempts = 0;
    $startedAt = microtime(true);

    MetadataDescriptionAgent::fake(function () use (&$attempts) {
        $attempts++;

        $response = new Response(new PsrResponse(
            429,
            [],
            '{"error":{"message":"please try again after 1 seconds","type":"rate_limit_reached_error"}}'
        ));

        throw RateLimitedException::forProvider(
            'anthropic',
            0,
            new RequestException($response),
        );
    });

    $post = Post::make([
        'title' => 'Retry after post',
        'link' => 'https://example.test/posts/retry-after',
    ]);

    app(EnrichmentService::class)->generateSummary($post);

    expect($attempts)->toBe(3)
        ->and(microtime(true) - $startedAt)->toBeGreaterThan(1.5);
});
