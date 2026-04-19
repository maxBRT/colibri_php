<?php

use App\Jobs\FetchRssJob;
use App\Models\Post;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('it writes new rss items as processing posts', function () {
    $source = Source::query()->create([
        'id' => 'source-tech',
        'name' => 'Tech Source',
        'url' => 'https://source.test/rss.xml',
        'category' => 'tech',
    ]);

    Http::fake([
        $source->url => Http::response(rssFeed([
            [
                'title' => 'First fetched post',
                'link' => 'https://source.test/posts/first',
                'guid' => 'first-guid',
                'pubDate' => 'Sat, 19 Apr 2026 12:00:00 GMT',
                'description' => 'First fetched description',
            ],
            [
                'title' => 'Second fetched post',
                'link' => 'https://source.test/posts/second',
                'guid' => 'second-guid',
                'pubDate' => 'Sat, 19 Apr 2026 13:00:00 GMT',
                'description' => 'Second fetched description',
            ],
        ]), 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    app()->call([new FetchRssJob, 'handle']);

    $this->assertDatabaseCount('posts', 2);

    $this->assertDatabaseHas('posts', [
        'source_id' => 'source-tech',
        'guid' => 'first-guid',
        'title' => 'First fetched post',
        'description' => null,
        'status' => 'processing',
    ]);

    $this->assertDatabaseHas('posts', [
        'source_id' => 'source-tech',
        'guid' => 'second-guid',
        'title' => 'Second fetched post',
        'description' => null,
        'status' => 'processing',
    ]);
});

test('it skips duplicate guids during rss ingestion', function () {
    $source = Source::query()->create([
        'id' => 'source-world',
        'name' => 'World Source',
        'url' => 'https://world.test/rss.xml',
        'category' => 'news',
    ]);

    Post::query()->create([
        'title' => 'Existing duplicate post',
        'description' => 'Already in database',
        'link' => 'https://world.test/posts/already-there',
        'guid' => 'duplicate-guid',
        'pub_date' => '2026-04-19 10:00:00',
        'source_id' => $source->id,
        'status' => 'done',
    ]);

    Http::fake([
        $source->url => Http::response(rssFeed([
            [
                'title' => 'Duplicate fetched post',
                'link' => 'https://world.test/posts/already-there',
                'guid' => 'duplicate-guid',
                'pubDate' => 'Sat, 19 Apr 2026 12:00:00 GMT',
                'description' => 'Should be skipped',
            ],
            [
                'title' => 'Brand new fetched post',
                'link' => 'https://world.test/posts/new',
                'guid' => 'new-guid',
                'pubDate' => 'Sat, 19 Apr 2026 13:00:00 GMT',
                'description' => 'Should be inserted',
            ],
        ]), 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    app()->call([new FetchRssJob, 'handle']);

    $this->assertDatabaseCount('posts', 2);

    expect(Post::query()->where('guid', 'duplicate-guid')->count())->toBe(1);

    $this->assertDatabaseHas('posts', [
        'source_id' => $source->id,
        'guid' => 'new-guid',
        'title' => 'Brand new fetched post',
        'description' => null,
        'status' => 'processing',
    ]);
});

test('it can be limited to specific source ids', function () {
    $techSource = Source::query()->create([
        'id' => 'source-tech',
        'name' => 'Tech Source',
        'url' => 'https://source.test/rss.xml',
        'category' => 'tech',
    ]);

    $worldSource = Source::query()->create([
        'id' => 'source-world',
        'name' => 'World Source',
        'url' => 'https://world.test/rss.xml',
        'category' => 'news',
    ]);

    Http::fake([
        $techSource->url => Http::response(rssFeed([
            [
                'title' => 'Tech post',
                'link' => 'https://source.test/posts/first',
                'guid' => 'tech-guid',
                'pubDate' => 'Sat, 19 Apr 2026 12:00:00 GMT',
                'description' => 'ignored',
            ],
        ]), 200, ['Content-Type' => 'application/rss+xml']),
        $worldSource->url => Http::response(rssFeed([
            [
                'title' => 'World post',
                'link' => 'https://world.test/posts/first',
                'guid' => 'world-guid',
                'pubDate' => 'Sat, 19 Apr 2026 12:00:00 GMT',
                'description' => 'ignored',
            ],
        ]), 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    app()->call([new FetchRssJob(['source-tech']), 'handle']);

    $this->assertDatabaseHas('posts', [
        'source_id' => 'source-tech',
        'guid' => 'tech-guid',
    ]);

    $this->assertDatabaseMissing('posts', [
        'source_id' => 'source-world',
        'guid' => 'world-guid',
    ]);
});

function rssFeed(array $items): string
{
    $xmlItems = collect($items)
        ->map(function (array $item): string {
            return sprintf(
                '<item><title>%s</title><link>%s</link><guid>%s</guid><pubDate>%s</pubDate><description>%s</description></item>',
                e($item['title']),
                e($item['link']),
                e($item['guid']),
                e($item['pubDate']),
                e($item['description'])
            );
        })
        ->implode('');

    return sprintf('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel>%s</channel></rss>', $xmlItems);
}
