<?php

use App\Models\Logo;
use App\Models\Post;
use App\Models\Source;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('source model key is string and non incrementing', function () {
    $source = new Source;

    expect($source->getKeyName())->toBe('id')
        ->and($source->getIncrementing())->toBeFalse()
        ->and($source->getKeyType())->toBe('string');
});

test('post and logo ids are uuid generated', function () {
    Source::query()->create([
        'id' => 'source-tech',
        'name' => 'Tech Source',
        'url' => 'https://example.com/feed.xml',
        'category' => 'tech',
    ]);

    $post = Post::query()->create([
        'title' => 'Hello',
        'description' => null,
        'link' => 'https://example.com/posts/1',
        'guid' => 'guid-1',
        'pub_date' => now(),
        'source_id' => 'source-tech',
        'status' => 'processing',
    ]);

    $logo = Logo::query()->create([
        'source_id' => 'source-tech',
        'object_key' => 'logos/source-tech.png',
        'url' => 'https://cdn.example.com/source-tech.png',
    ]);

    expect($post->id)->toMatch('/^[0-9a-fA-F-]{36}$/')
        ->and($logo->id)->toMatch('/^[0-9a-fA-F-]{36}$/');
});

test('foundation relations match blueprint', function () {
    $source = Source::query()->create([
        'id' => 'source-news',
        'name' => 'News Source',
        'url' => 'https://example.com/news.xml',
        'category' => 'news',
    ]);

    $post = Post::query()->create([
        'title' => 'News',
        'description' => 'desc',
        'link' => 'https://example.com/posts/2',
        'guid' => 'guid-2',
        'pub_date' => now(),
        'source_id' => $source->id,
        'status' => 'processing',
    ]);

    $logo = Logo::query()->create([
        'source_id' => $source->id,
        'object_key' => 'logos/source-news.png',
        'url' => 'https://cdn.example.com/source-news.png',
    ]);

    expect($source->posts())->toBeInstanceOf(HasMany::class)
        ->and($source->logo())->toBeInstanceOf(HasOne::class)
        ->and($post->source())->toBeInstanceOf(BelongsTo::class)
        ->and($logo->source())->toBeInstanceOf(BelongsTo::class)
        ->and($source->posts()->count())->toBe(1)
        ->and($source->logo?->id)->toBe($logo->id)
        ->and($post->source?->id)->toBe($source->id)
        ->and($logo->source?->id)->toBe($source->id);
});
