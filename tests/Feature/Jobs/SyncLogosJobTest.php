<?php

use App\Models\Logo;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('it syncs logos only for sources missing logos', function () {
    Storage::fake('s3');
    config()->set('filesystems.disks.s3.url', 'https://d1mc6q6crhpy86.cloudfront.net');

    $sourceMissingLogo = Source::query()->create([
        'id' => 'source-missing-logo',
        'name' => 'Missing Logo Source',
        'url' => 'https://missing-logo.test/rss.xml',
        'category' => 'tech',
    ]);

    $sourceHasLogo = Source::query()->create([
        'id' => 'source-has-logo',
        'name' => 'Has Logo Source',
        'url' => 'https://has-logo.test/rss.xml',
        'category' => 'news',
    ]);

    Logo::query()->create([
        'source_id' => $sourceHasLogo->id,
        'object_key' => 'logos/source-has-logo.png',
        'url' => 'https://cdn.example.com/source-has-logo.png',
        'mime_type' => 'image/png',
        'size_bytes' => 100,
    ]);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+YHkAAAAASUVORK5CYII=');

    Http::fake([
        'https://missing-logo.test/*' => Http::response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($png),
        ]),
        'https://has-logo.test/*' => Http::response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($png),
        ]),
    ]);

    $jobClass = 'App\\Jobs\\SyncLogosJob';

    app()->call([new $jobClass, 'handle']);

    $this->assertDatabaseCount('logos', 2);

    $this->assertDatabaseHas('logos', [
        'source_id' => $sourceMissingLogo->id,
        'url' => 'https://d1mc6q6crhpy86.cloudfront.net/logos/source-missing-logo.png',
        'mime_type' => 'image/png',
    ]);

    $this->assertDatabaseHas('logos', [
        'source_id' => $sourceHasLogo->id,
        'object_key' => 'logos/source-has-logo.png',
    ]);

    $createdLogo = Logo::query()->where('source_id', $sourceMissingLogo->id)->firstOrFail();
    expect(Storage::disk('s3')->exists($createdLogo->object_key))->toBeTrue();

    Http::assertNotSent(function ($request): bool {
        return str_contains($request->url(), 'has-logo.test');
    });
});
