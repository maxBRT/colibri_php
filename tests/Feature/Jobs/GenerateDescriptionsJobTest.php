<?php

use App\Jobs\GenerateDescriptionsJob;
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

    app()->call([new GenerateDescriptionsJob(limit: 10), 'handle']);

    $post->refresh();

    expect($post->status)->toBe('done')
        ->and($post->description)->toBe('AI summary for pending post.');
});

test('it keeps post processing with null description when enrichment fails after retries are exhausted', function () {
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

    app()->call([new GenerateDescriptionsJob(limit: 10), 'handle']);

    $post->refresh();

    expect($post->status)->toBe('processing')
        ->and($post->description)->toBeNull();
});

test('it processes only pending posts and respects limit', function () {
    $source = Source::query()->create([
        'id' => 'source-limit',
        'name' => 'Limit Source',
        'url' => 'https://example.test/limit.xml',
        'category' => 'news',
    ]);

    $donePost = Post::query()->create([
        'title' => 'Already done',
        'description' => 'Existing description',
        'link' => 'https://example.test/posts/done',
        'guid' => 'done-guid',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'done',
    ]);

    $pendingA = Post::query()->create([
        'title' => 'Pending A',
        'description' => null,
        'link' => 'https://example.test/posts/a',
        'guid' => 'pending-a',
        'pub_date' => now()->subMinutes(2),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $pendingB = Post::query()->create([
        'title' => 'Pending B',
        'description' => null,
        'link' => 'https://example.test/posts/b',
        'guid' => 'pending-b',
        'pub_date' => now()->subMinute(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $pendingC = Post::query()->create([
        'title' => 'Pending C',
        'description' => null,
        'link' => 'https://example.test/posts/c',
        'guid' => 'pending-c',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $service = Mockery::mock(EnrichmentService::class);
    $service->shouldReceive('generateSummary')->times(2)->andReturn('Limited summary');
    app()->instance(EnrichmentService::class, $service);

    app()->call([new GenerateDescriptionsJob(limit: 2), 'handle']);

    $donePost->refresh();
    $pendingA->refresh();
    $pendingB->refresh();
    $pendingC->refresh();

    expect($donePost->status)->toBe('done')
        ->and($donePost->description)->toBe('Existing description')
        ->and(collect([$pendingA, $pendingB, $pendingC])->where('status', 'done'))->toHaveCount(2)
        ->and(collect([$pendingA, $pendingB, $pendingC])->where('status', 'processing'))->toHaveCount(1);
});

test('it continues processing batch when one post enrichment throws', function () {
    $source = Source::query()->create([
        'id' => 'source-errors',
        'name' => 'Error Source',
        'url' => 'https://example.test/errors.xml',
        'category' => 'news',
    ]);

    $postOne = Post::query()->create([
        'title' => 'First pending',
        'description' => null,
        'link' => 'https://example.test/posts/one',
        'guid' => 'pending-one',
        'pub_date' => now()->subMinutes(2),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $postTwo = Post::query()->create([
        'title' => 'Second pending',
        'description' => null,
        'link' => 'https://example.test/posts/two',
        'guid' => 'pending-two',
        'pub_date' => now()->subMinute(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $postThree = Post::query()->create([
        'title' => 'Third pending',
        'description' => null,
        'link' => 'https://example.test/posts/three',
        'guid' => 'pending-three',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $service = Mockery::mock(EnrichmentService::class);
    $service->shouldReceive('generateSummary')->andReturn('Summary one', null, 'Summary three');
    app()->instance(EnrichmentService::class, $service);

    app()->call([new GenerateDescriptionsJob(limit: 10), 'handle']);

    $postOne->refresh();
    $postTwo->refresh();
    $postThree->refresh();

    expect($postOne->status)->toBe('done')
        ->and($postOne->description)->toBe('Summary one')
        ->and($postTwo->status)->toBe('processing')
        ->and($postTwo->description)->toBeNull()
        ->and($postThree->status)->toBe('done')
        ->and($postThree->description)->toBe('Summary three');
});

test('it defines enrichment queue and retry configuration', function () {
    $job = new GenerateDescriptionsJob(limit: 25);

    expect($job->queue)->toBe('enrichment')
        ->and($job->tries)->toBeInt()
        ->and($job->tries)->toBeGreaterThan(0)
        ->and($job->backoff)->toBeArray()
        ->and($job->backoff)->not->toBeEmpty()
        ->and($job->timeout)->toBeInt()
        ->and($job->timeout)->toBeGreaterThan(0);
});
