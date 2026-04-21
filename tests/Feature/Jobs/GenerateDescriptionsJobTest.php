<?php

use App\Jobs\GenerateDescriptionForPostJob;
use App\Jobs\GenerateDescriptionsJob;
use App\Models\Post;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('it dispatches individual jobs for each pending post', function () {
    Queue::fake();

    $source = Source::query()->create([
        'id' => 'source-tech',
        'name' => 'Tech Source',
        'url' => 'https://example.test/rss.xml',
        'category' => 'tech',
    ]);

    $postA = Post::query()->create([
        'title' => 'Pending post A',
        'description' => null,
        'link' => 'https://example.test/posts/1',
        'guid' => 'pending-guid-1',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $postB = Post::query()->create([
        'title' => 'Pending post B',
        'description' => null,
        'link' => 'https://example.test/posts/2',
        'guid' => 'pending-guid-2',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    app()->call([new GenerateDescriptionsJob(limit: 10), 'handle']);

    Queue::assertPushed(GenerateDescriptionForPostJob::class, 2);
    Queue::assertPushed(GenerateDescriptionForPostJob::class, function (GenerateDescriptionForPostJob $job) use ($postA) {
        return $job->post->id === $postA->id;
    });
    Queue::assertPushed(GenerateDescriptionForPostJob::class, function (GenerateDescriptionForPostJob $job) use ($postB) {
        return $job->post->id === $postB->id;
    });
});

test('it does not dispatch jobs for already done posts', function () {
    Queue::fake();

    $source = Source::query()->create([
        'id' => 'source-tech',
        'name' => 'Tech Source',
        'url' => 'https://example.test/rss.xml',
        'category' => 'tech',
    ]);

    Post::query()->create([
        'title' => 'Already done',
        'description' => 'Existing description',
        'link' => 'https://example.test/posts/done',
        'guid' => 'done-guid',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'done',
    ]);

    app()->call([new GenerateDescriptionsJob(limit: 10), 'handle']);

    Queue::assertNothingPushed();
});

test('it respects the limit when dispatching jobs', function () {
    Queue::fake();

    $source = Source::query()->create([
        'id' => 'source-limit',
        'name' => 'Limit Source',
        'url' => 'https://example.test/limit.xml',
        'category' => 'news',
    ]);

    Post::query()->create([
        'title' => 'Pending A',
        'description' => null,
        'link' => 'https://example.test/posts/a',
        'guid' => 'pending-a',
        'pub_date' => now()->subMinutes(2),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    Post::query()->create([
        'title' => 'Pending B',
        'description' => null,
        'link' => 'https://example.test/posts/b',
        'guid' => 'pending-b',
        'pub_date' => now()->subMinute(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    Post::query()->create([
        'title' => 'Pending C',
        'description' => null,
        'link' => 'https://example.test/posts/c',
        'guid' => 'pending-c',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    app()->call([new GenerateDescriptionsJob(limit: 2), 'handle']);

    Queue::assertPushed(GenerateDescriptionForPostJob::class, 2);
});

test('it defines enrichment queue configuration', function () {
    $job = new GenerateDescriptionsJob(limit: 25);

    expect($job->queue)->toBe('enrichment');
});
