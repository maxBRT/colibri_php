<?php

use App\Ai\Agents\MetadataDescriptionAgent;
use App\Models\Post;
use App\Services\EnrichmentService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('ai.default', 'gemini');
    config()->set('ai.providers.gemini.model', 'gemini-2.5-flash');
    config()->set('ai.providers.gemini.retries', 3);
    config()->set('ai.providers.gemini.retry_sleep_ms', 0);
});

test('it returns summary string when gemini responds successfully', function () {
    MetadataDescriptionAgent::fake(['This is a generated summary.']);

    $post = Post::make([
        'title' => 'Test post',
        'link' => 'https://example.test/posts/1',
    ]);

    $summary = app(EnrichmentService::class)->generateSummary($post);

    expect($summary)->toBe('This is a generated summary.');
});

test('it sends expected payload to gemini', function () {
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

        throw new RuntimeException('Gemini unavailable');
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
