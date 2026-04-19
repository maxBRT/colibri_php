<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('logo.connect_timeout', 2);
    config()->set('logo.timeout', 5);
    config()->set('logo.retry_times', 1);
    config()->set('logo.retry_sleep_ms', 0);
    config()->set('logo.max_size_bytes', 1024 * 1024);
});

function discoverFromFeedUrl(string $feedUrl): ?array
{
    $serviceClass = 'App\\Services\\LogoDiscoveryService';

    return app($serviceClass)->discoverFromFeedUrl($feedUrl);
}

test('it parses feed url and requests site base url before discovery', function () {
    $png = validPngBytes();

    Http::fake([
        'https://example.test/' => Http::response(homepageWithIcon('/assets/favicon-32x32.png'), 200, ['Content-Type' => 'text/html']),
        'https://example.test/assets/favicon-32x32.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'https://example.test/rss.xml' => Http::response('rss endpoint should not be called', 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    $result = discoverFromFeedUrl('https://example.test/rss.xml');

    Http::assertSent(function (Request $request): bool {
        $parts = parse_url($request->url());
        $path = $parts['path'] ?? '/';

        return ($parts['host'] ?? null) === 'example.test' && ($path === '' || $path === '/');
    });

    Http::assertNotSent(function (Request $request): bool {
        return $request->url() === 'https://example.test/rss.xml';
    });

    expect($result)->not->toBeNull();
});

test('it discovers logo from html link rel icon and returns normalized payload', function () {
    $png = validPngBytes();

    Http::fake([
        'https://example.test/' => Http::response(homepageWithIcon('/assets/favicon-32x32.png'), 200, ['Content-Type' => 'text/html']),
        'https://example.test/assets/favicon-32x32.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    $result = discoverFromFeedUrl('https://example.test/rss.xml');

    expect($result)->toBeArray()
        ->and($result)->toMatchArray([
            'logo_url' => 'https://example.test/assets/favicon-32x32.png',
            'mime_type' => 'image/png',
            'size_bytes' => strlen($png),
            'extension' => 'png',
        ])
        ->and($result['bytes'])->toBe($png);
});

test('it falls back to favicon ico when html has no icon link', function () {
    $ico = str_repeat('I', 128);

    Http::fake([
        'https://example.test/' => Http::response(homepageWithoutIcon(), 200, ['Content-Type' => 'text/html']),
        'https://example.test/favicon.ico' => Http::response($ico, 200, ['Content-Type' => 'image/x-icon']),
    ]);

    $result = discoverFromFeedUrl('https://example.test/rss.xml');

    expect($result)->toBeArray()
        ->and($result)->toMatchArray([
            'logo_url' => 'https://example.test/favicon.ico',
            'mime_type' => 'image/x-icon',
            'size_bytes' => strlen($ico),
            'extension' => 'ico',
        ])
        ->and($result['bytes'])->toBe($ico);
});

test('it returns null when candidate response content type is not image', function () {
    Http::fake([
        'https://example.test/' => Http::response(homepageWithIcon('/assets/not-image.txt'), 200, ['Content-Type' => 'text/html']),
        'https://example.test/assets/not-image.txt' => Http::response('not an image', 200, ['Content-Type' => 'text/plain']),
    ]);

    $result = discoverFromFeedUrl('https://example.test/rss.xml');

    expect($result)->toBeNull();
});

test('it returns null when discovered image exceeds max configured size', function () {
    config()->set('logo.max_size_bytes', 10);

    $largePng = str_repeat('A', 32);

    Http::fake([
        'https://example.test/' => Http::response(homepageWithIcon('/assets/large.png'), 200, ['Content-Type' => 'text/html']),
        'https://example.test/assets/large.png' => Http::response($largePng, 200, ['Content-Type' => 'image/png']),
    ]);

    $result = discoverFromFeedUrl('https://example.test/rss.xml');

    expect($result)->toBeNull();
});

test('it returns null when homepage and fallback are unavailable', function () {
    Http::fake([
        'https://example.test/' => Http::response('', 500),
        'https://example.test/favicon.ico' => Http::response('', 404),
    ]);

    $result = discoverFromFeedUrl('https://example.test/rss.xml');

    expect($result)->toBeNull();
});

function homepageWithIcon(string $href): string
{
    return sprintf(
        '<!doctype html><html><head><link rel="icon" href="%s"></head><body>ok</body></html>',
        $href
    );
}

function homepageWithoutIcon(): string
{
    return '<!doctype html><html><head><title>No icon</title></head><body>ok</body></html>';
}

function validPngBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+YHkAAAAASUVORK5CYII=');
}
