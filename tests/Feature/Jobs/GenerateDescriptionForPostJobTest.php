<?php

use App\Jobs\GenerateDescriptionForPostJob;
use App\Models\Post;
use App\Models\Source;
use App\Services\EnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it marks pending post done and stores description on successful enrichment', function () {
    $source = Source::query()->create([
        'id' => 'source-tech',
        'name' => 'Tech Source',
        'url' => 'https://example.test/rss.xml',
        'category' => 'tech',
    ]);

    $post = Post::query()->create([
        'title' => 'Pending post',
        'description' => null,
        'link' => 'https://example.test/posts/1',
        'guid' => 'pending-guid-1',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $service = Mockery::mock(EnrichmentService::class);
    $service->shouldReceive('generateSummary')->once()->with(Mockery::type(Post::class))->andReturn('AI summary for pending post.');
    app()->instance(EnrichmentService::class, $service);

    app()->call([new GenerateDescriptionForPostJob($post), 'handle']);

    $post->refresh();

    expect($post->status)->toBe('done')
        ->and($post->description)->toBe('AI summary for pending post.');
});

test('it keeps post processing with null description when enrichment returns null', function () {
    $source = Source::query()->create([
        'id' => 'source-news',
        'name' => 'News Source',
        'url' => 'https://example.test/news.xml',
        'category' => 'news',
    ]);

    $post = Post::query()->create([
        'title' => 'Pending fail-soft post',
        'description' => null,
        'link' => 'https://example.test/posts/2',
        'guid' => 'pending-guid-2',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $service = Mockery::mock(EnrichmentService::class);
    $service->shouldReceive('generateSummary')->once()->andReturn(null);
    app()->instance(EnrichmentService::class, $service);

    app()->call([new GenerateDescriptionForPostJob($post), 'handle']);

    $post->refresh();

    expect($post->status)->toBe('processing')
        ->and($post->description)->toBeNull();
});

test('it defines enrichment queue and retry configuration', function () {
    $source = Source::query()->create([
        'id' => 'source-test',
        'name' => 'Test Source',
        'url' => 'https://example.test/test.xml',
        'category' => 'tech',
    ]);

    $post = Post::query()->create([
        'title' => 'Test post',
        'description' => null,
        'link' => 'https://example.test/posts/test',
        'guid' => 'test-guid',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $job = new GenerateDescriptionForPostJob($post);

    expect($job->queue)->toBe('enrichment')
        ->and($job->tries)->toBeInt()
        ->and($job->tries)->toBeGreaterThan(0)
        ->and($job->backoff)->toBeArray()
        ->and($job->backoff)->not->toBeEmpty()
        ->and($job->timeout)->toBeInt()
        ->and($job->timeout)->toBeGreaterThan(0);
});
