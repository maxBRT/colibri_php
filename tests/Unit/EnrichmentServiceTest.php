<?php

use App\Ai\Agents\MetadataDescriptionAgent;
use App\Ai\Tools\FetchUrl;
use App\Models\Post;
use App\Services\EnrichmentService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\RateLimitedException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('ai.default', 'moonshot');
    config()->set('ai.providers.moonshot.driver', 'groq');
    config()->set('ai.providers.moonshot.model', 'kimi-k2.5');
    config()->set('ai.providers.moonshot.retries', 3);
    config()->set('ai.providers.moonshot.retry_sleep_ms', 0);

    $this->mock(FetchUrl::class, function ($mock) {
        $mock->shouldReceive('fetch')->andReturn('Sample article content for testing.');
    });
});

test('moonshot provider uses chat completions compatible driver', function () {
    expect(config('ai.providers.moonshot.driver'))->toBe('groq');
});

test('it returns summary string when kimi responds successfully', function () {
    MetadataDescriptionAgent::fake(['This is a generated summary.']);

    $post = Post::make([
        'title' => 'Test post',
        'link' => 'https://example.test/posts/1',
    ]);

    $summary = app(EnrichmentService::class)->generateSummary($post);

    expect($summary)->toBe('This is a generated summary.');
});

test('it sends expected payload to kimi', function () {
    $this->mock(FetchUrl::class, function ($mock) {
        $mock->shouldReceive('fetch')
            ->once()
            ->with('https://example.test/posts/payload')
            ->andReturn('Fetched article body for testing.');
    });

    MetadataDescriptionAgent::fake(['Summary payload check.']);

    $post = Post::make([
        'title' => 'Payload post',
        'link' => 'https://example.test/posts/payload',
    ]);

    app(EnrichmentService::class)->generateSummary($post);

    MetadataDescriptionAgent::assertPrompted(function ($prompt) {
        return $prompt->contains('Payload post')
            && $prompt->contains('https://example.test/posts/payload')
            && $prompt->contains('Fetched article body for testing.');
    });
});

test('it retries and returns null on repeated network exception', function () {
    $attempts = 0;

    MetadataDescriptionAgent::fake(function () use (&$attempts) {
        $attempts++;

        throw new RuntimeException('Kimi unavailable');
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

test('it logs success when kimi responds with a usable summary', function () {
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

test('it logs warning when kimi returns no usable description', function () {
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
                return $context['error'] === 'Kimi unavailable'
                    && $context['exception'] === RuntimeException::class;
            }

            return $message === 'LLM enrichment failed after all retries.'
                && $context['attempts'] === 3
                && $context['error'] === 'Kimi unavailable'
                && $context['exception'] === RuntimeException::class;
        });

    MetadataDescriptionAgent::fake(function () {
        throw new RuntimeException('Kimi unavailable');
    });

    $post = Post::make([
        'title' => 'Logged failure post',
        'link' => 'https://example.test/posts/logged-failure',
    ]);

    app(EnrichmentService::class)->generateSummary($post);

    Exceptions::assertReported(RuntimeException::class);
});

test('it does not report expected moonshot rate limit failures', function () {
    Exceptions::fake();

    MetadataDescriptionAgent::fake(function () {
        throw RateLimitedException::forProvider('moonshot');
    });

    $post = Post::make([
        'title' => 'Rate limited post',
        'link' => 'https://example.test/posts/rate-limited',
    ]);

    app(EnrichmentService::class)->generateSummary($post);

    Exceptions::assertNothingReported();
});

test('it honors moonshot retry-after hints when rate limited', function () {
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
            'moonshot',
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
