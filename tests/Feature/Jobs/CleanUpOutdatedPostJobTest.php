<?php

use App\Jobs\CleanUpOutdatedPostJob;
use App\Models\Post;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it deletes posts older than 7 days', function () {
    $this->travelTo(now()->startOfDay());

    $source = Source::query()->create([
        'id' => 'source-cleanup',
        'name' => 'Cleanup Source',
        'url' => 'https://cleanup.test/rss.xml',
        'category' => 'news',
    ]);

    Post::query()->create([
        'title' => 'Old done post',
        'description' => 'outdated',
        'link' => 'https://cleanup.test/posts/old-done',
        'guid' => 'old-done-guid',
        'pub_date' => now()->subDays(8),
        'source_id' => $source->id,
        'status' => 'done',
    ]);

    Post::query()->create([
        'title' => 'Old processing post',
        'description' => null,
        'link' => 'https://cleanup.test/posts/old-processing',
        'guid' => 'old-processing-guid',
        'pub_date' => now()->subDays(15),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    Post::query()->create([
        'title' => 'Fresh post',
        'description' => 'keep me',
        'link' => 'https://cleanup.test/posts/fresh',
        'guid' => 'fresh-guid',
        'pub_date' => now()->subDays(6),
        'source_id' => $source->id,
        'status' => 'done',
    ]);

    app()->call([new CleanUpOutdatedPostJob, 'handle']);

    $this->assertDatabaseMissing('posts', ['guid' => 'old-done-guid']);
    $this->assertDatabaseMissing('posts', ['guid' => 'old-processing-guid']);
    $this->assertDatabaseHas('posts', ['guid' => 'fresh-guid']);
    $this->assertDatabaseCount('posts', 1);
});

test('it keeps posts at 7-day boundary and removes older ones', function () {
    $this->travelTo(now()->startOfSecond());

    $source = Source::query()->create([
        'id' => 'source-boundary',
        'name' => 'Boundary Source',
        'url' => 'https://boundary.test/rss.xml',
        'category' => 'tech',
    ]);

    Post::query()->create([
        'title' => 'Boundary post',
        'description' => 'on edge',
        'link' => 'https://boundary.test/posts/boundary',
        'guid' => 'boundary-guid',
        'pub_date' => now()->subDays(7),
        'source_id' => $source->id,
        'status' => 'done',
    ]);

    Post::query()->create([
        'title' => 'Just outdated post',
        'description' => 'just outside retention',
        'link' => 'https://boundary.test/posts/just-outdated',
        'guid' => 'just-outdated-guid',
        'pub_date' => now()->subDays(8),
        'source_id' => $source->id,
        'status' => 'done',
    ]);

    app()->call([new CleanUpOutdatedPostJob, 'handle']);

    $this->assertDatabaseHas('posts', ['guid' => 'boundary-guid']);
    $this->assertDatabaseMissing('posts', ['guid' => 'just-outdated-guid']);
});

test('it is idempotent when cleanup job runs more than once', function () {
    $this->travelTo(now()->startOfDay());

    $source = Source::query()->create([
        'id' => 'source-idempotent',
        'name' => 'Idempotent Source',
        'url' => 'https://idempotent.test/rss.xml',
        'category' => 'news',
    ]);

    Post::query()->create([
        'title' => 'Old post',
        'description' => 'delete once',
        'link' => 'https://idempotent.test/posts/old',
        'guid' => 'idempotent-old-guid',
        'pub_date' => now()->subDays(10),
        'source_id' => $source->id,
        'status' => 'done',
    ]);

    Post::query()->create([
        'title' => 'Recent post',
        'description' => 'keep always',
        'link' => 'https://idempotent.test/posts/recent',
        'guid' => 'idempotent-recent-guid',
        'pub_date' => now()->subDays(2),
        'source_id' => $source->id,
        'status' => 'done',
    ]);

    app()->call([new CleanUpOutdatedPostJob, 'handle']);
    app()->call([new CleanUpOutdatedPostJob, 'handle']);

    $this->assertDatabaseMissing('posts', ['guid' => 'idempotent-old-guid']);
    $this->assertDatabaseHas('posts', ['guid' => 'idempotent-recent-guid']);
    $this->assertDatabaseCount('posts', 1);
});
