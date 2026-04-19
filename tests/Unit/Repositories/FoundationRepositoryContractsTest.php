<?php

use App\Models\Source;
use App\Repositories\Contracts\LogoRepositoryInterface;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Repositories\Contracts\SourceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('repository interfaces exist with blueprint methods', function () {
    expect(interface_exists(SourceRepositoryInterface::class))->toBeTrue()
        ->and(interface_exists(PostRepositoryInterface::class))->toBeTrue()
        ->and(interface_exists(LogoRepositoryInterface::class))->toBeTrue();

    $source = new ReflectionClass(SourceRepositoryInterface::class);
    $post = new ReflectionClass(PostRepositoryInterface::class);
    $logo = new ReflectionClass(LogoRepositoryInterface::class);

    expect($source->hasMethod('list'))->toBeTrue()
        ->and($source->hasMethod('upsert'))->toBeTrue()
        ->and($source->hasMethod('listCategories'))->toBeTrue()
        ->and($post->hasMethod('paginate'))->toBeTrue()
        ->and($post->hasMethod('findByGuid'))->toBeTrue()
        ->and($post->hasMethod('insert'))->toBeTrue()
        ->and($post->hasMethod('listPending'))->toBeTrue()
        ->and($post->hasMethod('markDone'))->toBeTrue()
        ->and($logo->hasMethod('findBySourceId'))->toBeTrue()
        ->and($logo->hasMethod('upsert'))->toBeTrue()
        ->and($logo->hasMethod('listSourcesWithoutLogos'))->toBeTrue();
});

test('source repository list filter upsert and categories behavior', function () {
    /** @var SourceRepositoryInterface $repo */
    $repo = app(SourceRepositoryInterface::class);

    $repo->upsert([
        'id' => 'source-a',
        'name' => 'Source A',
        'url' => 'https://example.com/a.xml',
        'category' => 'tech',
    ]);

    $repo->upsert([
        'id' => 'source-b',
        'name' => 'Source B',
        'url' => 'https://example.com/b.xml',
        'category' => 'news',
    ]);

    $repo->upsert([
        'id' => 'source-a',
        'name' => 'Source A (updated)',
        'url' => 'https://example.com/a-new.xml',
        'category' => 'tech',
    ]);

    $all = $repo->list(null);
    $filtered = $repo->list(['tech']);
    $categories = $repo->listCategories();

    expect($all)->toHaveCount(2)
        ->and($filtered)->toHaveCount(1)
        ->and(collect($categories))->toContain('tech', 'news');

    expect(Source::query()->find('source-a')?->name)->toBe('Source A (updated)');
});

test('post repository paginate find by guid list pending mark done and insert behavior', function () {
    /** @var SourceRepositoryInterface $sourceRepo */
    $sourceRepo = app(SourceRepositoryInterface::class);
    $sourceRepo->upsert([
        'id' => 'source-tech',
        'name' => 'Tech',
        'url' => 'https://example.com/tech.xml',
        'category' => 'tech',
    ]);

    /** @var PostRepositoryInterface $postRepo */
    $postRepo = app(PostRepositoryInterface::class);

    $inserted = $postRepo->insert([
        'title' => 'First',
        'description' => null,
        'link' => 'https://example.com/p/1',
        'guid' => 'guid-100',
        'pub_date' => now(),
        'source_id' => 'source-tech',
        'status' => 'processing',
    ]);

    $found = $postRepo->findByGuid('guid-100');
    $pending = $postRepo->listPending(10);
    $page = $postRepo->paginate(['sources' => ['source-tech']], 1, 20);

    expect($inserted)->not->toBeNull()
        ->and($found)->not->toBeNull()
        ->and($pending)->toHaveCount(1)
        ->and($page)->toBeInstanceOf(LengthAwarePaginator::class);

    $postRepo->markDone((string) $found->id, 'done description');

    $updated = $postRepo->findByGuid('guid-100');

    expect($updated->status)->toBe('done')
        ->and($updated->description)->toBe('done description');
});

test('logo repository upsert find by source id and list sources without logos behavior', function () {
    /** @var SourceRepositoryInterface $sourceRepo */
    $sourceRepo = app(SourceRepositoryInterface::class);
    $sourceRepo->upsert([
        'id' => 'source-1',
        'name' => 'One',
        'url' => 'https://example.com/1.xml',
        'category' => 'tech',
    ]);
    $sourceRepo->upsert([
        'id' => 'source-2',
        'name' => 'Two',
        'url' => 'https://example.com/2.xml',
        'category' => 'news',
    ]);

    /** @var LogoRepositoryInterface $logoRepo */
    $logoRepo = app(LogoRepositoryInterface::class);

    $logoRepo->upsert([
        'source_id' => 'source-1',
        'object_key' => 'logos/source-1.png',
        'url' => 'https://cdn.example.com/source-1.png',
        'mime_type' => 'image/png',
        'size_bytes' => 12345,
    ]);

    $logoRepo->upsert([
        'source_id' => 'source-1',
        'object_key' => 'logos/source-1-new.png',
        'url' => 'https://cdn.example.com/source-1-new.png',
        'mime_type' => 'image/png',
        'size_bytes' => 54321,
    ]);

    $logo = $logoRepo->findBySourceId('source-1');
    $without = $logoRepo->listSourcesWithoutLogos();

    expect($logo)->not->toBeNull()
        ->and($logo->object_key)->toBe('logos/source-1-new.png')
        ->and(collect($without)->pluck('id'))->toContain('source-2')
        ->and(collect($without)->pluck('id'))->not->toContain('source-1');
});
