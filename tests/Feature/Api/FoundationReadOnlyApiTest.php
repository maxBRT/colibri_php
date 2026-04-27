<?php

use App\Models\Logo;
use App\Models\Post;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Source::query()->create([
        'id' => 'source-tech',
        'name' => 'Tech Source',
        'url' => 'https://example.com/tech.xml',
        'category' => 'tech',
    ]);

    Source::query()->create([
        'id' => 'source-news',
        'name' => 'News Source',
        'url' => 'https://example.com/news.xml',
        'category' => 'news',
    ]);

    Logo::query()->create([
        'source_id' => 'source-tech',
        'object_key' => 'logos/source-tech.png',
        'url' => 'https://cdn.example.com/source-tech.png',
        'mime_type' => 'image/png',
        'size_bytes' => 100,
    ]);

    Post::query()->create([
        'title' => 'Tech Post',
        'description' => 'desc 1',
        'link' => 'https://example.com/p/1',
        'guid' => 'guid-tech-1',
        'pub_date' => now()->subDay(),
        'source_id' => 'source-tech',
        'status' => 'done',
    ]);

    Post::query()->create([
        'title' => 'News Post',
        'description' => 'desc 2',
        'link' => 'https://example.com/p/2',
        'guid' => 'guid-news-1',
        'pub_date' => now(),
        'source_id' => 'source-news',
        'status' => 'processing',
    ]);
});

test('GET v1 categories returns categories envelope', function () {
    $this->getJson('/v1/categories')
        ->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) => $json->has('categories')
            ->whereType('categories', 'array')
            ->etc()
        );
});

test('GET v1 sources returns sources envelope and shape', function () {
    $this->getJson('/v1/sources')
        ->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) => $json->has('sources', 2)
            ->has('sources.0', fn (AssertableJson $json) => $json->whereType('id', 'string')
                ->whereType('name', 'string')
                ->whereType('url', 'string')
                ->whereType('category', 'string')
                ->whereType('logo_url', 'string|null')
                ->etc()
            )
            ->etc()
        );
});

test('GET v1 posts returns posts and pagination envelope', function () {
    $this->getJson('/v1/posts?page=1&per_page=20')
        ->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) => $json->has('posts')
            ->has('pagination', fn (AssertableJson $json) => $json->where('page', 1)
                ->where('per_page', 20)
                ->whereType('total', 'integer')
                ->whereType('total_pages', 'integer')
                ->etc()
            )
            ->etc()
        );
});

test('GET v1 posts supports sources filter', function () {
    $this->getJson('/v1/posts?sources[]=source-tech')
        ->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) => $json->has('posts')
            ->etc()
        );
});

test('GET v1 posts rejects invalid page lower bound', function () {
    $this->getJson('/v1/posts?page=0')
        ->assertStatus(422);
});

test('GET v1 posts rejects invalid per page lower bound', function () {
    $this->getJson('/v1/posts?per_page=0')
        ->assertStatus(422);
});

test('GET v1 posts rejects invalid per page upper bound above 100', function () {
    $this->getJson('/v1/posts?per_page=101')
        ->assertStatus(422);
});

test('GET v1 posts accepts per page max 100', function () {
    $this->getJson('/v1/posts?per_page=100')
        ->assertSuccessful();
});

test('GET v1 posts supports hours filter', function () {
    $this->getJson('/v1/posts?hours=12')
        ->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) => $json->has('posts', 1)
            ->has('posts.0', fn (AssertableJson $json) => $json->where('title', 'News Post')
                ->etc()
            )
            ->etc()
        );
});

test('GET v1 posts rejects invalid hours lower bound', function () {
    $this->getJson('/v1/posts?hours=0')
        ->assertStatus(422);
});

test('GET health returns basic healthy response', function () {
    $this->getJson('/health')
        ->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) => $json->has('status')
            ->etc()
        );
});
