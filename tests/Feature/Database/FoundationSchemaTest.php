<?php

use App\Models\Logo;
use App\Models\Post;
use App\Models\Source;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('posts guid is unique', function () {
    Source::query()->create([
        'id' => 's1',
        'name' => 'S1',
        'url' => 'https://example.com/s1.xml',
        'category' => 'tech',
    ]);

    Post::query()->create([
        'title' => 'A',
        'description' => null,
        'link' => 'https://example.com/a',
        'guid' => 'same-guid',
        'pub_date' => now(),
        'source_id' => 's1',
        'status' => 'processing',
    ]);

    expect(fn () => Post::query()->create([
        'title' => 'B',
        'description' => null,
        'link' => 'https://example.com/b',
        'guid' => 'same-guid',
        'pub_date' => now(),
        'source_id' => 's1',
        'status' => 'processing',
    ]))->toThrow(QueryException::class);
});

test('logos source id is unique one logo per source', function () {
    Source::query()->create([
        'id' => 's2',
        'name' => 'S2',
        'url' => 'https://example.com/s2.xml',
        'category' => 'news',
    ]);

    Logo::query()->create([
        'source_id' => 's2',
        'object_key' => 'logos/s2.png',
        'url' => 'https://cdn.example.com/s2.png',
    ]);

    expect(fn () => Logo::query()->create([
        'source_id' => 's2',
        'object_key' => 'logos/s2b.png',
        'url' => 'https://cdn.example.com/s2b.png',
    ]))->toThrow(QueryException::class);
});

test('posts source id enforces foreign key to sources id', function () {
    expect(fn () => Post::query()->create([
        'title' => 'Orphan',
        'description' => null,
        'link' => 'https://example.com/orphan',
        'guid' => 'orphan-guid',
        'pub_date' => now(),
        'source_id' => 'missing-source',
        'status' => 'processing',
    ]))->toThrow(QueryException::class);
});

test('logos source id enforces foreign key to sources id', function () {
    expect(fn () => Logo::query()->create([
        'source_id' => 'missing-source',
        'object_key' => 'logos/missing.png',
        'url' => 'https://cdn.example.com/missing.png',
    ]))->toThrow(QueryException::class);
});
