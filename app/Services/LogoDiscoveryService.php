<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class LogoDiscoveryService
{
    /**
     * @return array{logo_url: string, bytes: string, mime_type: string, size_bytes: int, extension: string}|null
     */
    public function discoverFromFeedUrl(string $feedUrl): ?array
    {
        $baseUrl = $this->baseUrlFromFeedUrl($feedUrl);

        if ($baseUrl === null) {
            return null;
        }

        $iconUrl = $this->discoverIconUrlFromHomepage($baseUrl);

        if ($iconUrl === null) {
            $iconUrl = $baseUrl.'favicon.ico';
        }

        return $this->fetchAndValidateIcon($iconUrl);
    }

    private function discoverIconUrlFromHomepage(string $baseUrl): ?string
    {
        $response = Http::connectTimeout((int) config('logo.connect_timeout', 5))
            ->timeout((int) config('logo.timeout', 10))
            ->retry((int) config('logo.retry_times', 1), (int) config('logo.retry_sleep_ms', 0))
            ->withUserAgent((string) config('logo.user_agent', 'ColibriLogo/1.0'))
            ->accept('text/html,application/xhtml+xml')
            ->get($baseUrl);

        if ($response->failed()) {
            return null;
        }

        $html = (string) $response->body();

        if ($html === '') {
            return null;
        }

        preg_match_all('/<link\b[^>]*>/i', $html, $matches);

        $candidates = [];

        foreach (Arr::get($matches, 0, []) as $linkTag) {
            $rel = $this->extractAttribute($linkTag, 'rel');
            $href = $this->extractAttribute($linkTag, 'href');

            if ($href === null || $href === '') {
                continue;
            }

            if ($rel === null || $rel === '') {
                continue;
            }

            $normalizedRel = strtolower($rel);

            if (
                str_contains($normalizedRel, 'icon')
                || str_contains($normalizedRel, 'shortcut icon')
                || str_contains($normalizedRel, 'apple-touch-icon')
            ) {
                $candidates[] = $this->toAbsoluteUrl($baseUrl, $href);
            }
        }

        return $candidates[0] ?? null;
    }

    /**
     * @return array{logo_url: string, bytes: string, mime_type: string, size_bytes: int, extension: string}|null
     */
    private function fetchAndValidateIcon(string $iconUrl): ?array
    {
        $response = Http::connectTimeout((int) config('logo.connect_timeout', 5))
            ->timeout((int) config('logo.timeout', 10))
            ->retry((int) config('logo.retry_times', 1), (int) config('logo.retry_sleep_ms', 0))
            ->withUserAgent((string) config('logo.user_agent', 'ColibriLogo/1.0'))
            ->get($iconUrl);

        if ($response->failed()) {
            return null;
        }

        $mimeType = strtolower(trim((string) $response->header('Content-Type')));
        $mimeType = explode(';', $mimeType)[0] ?? '';

        if (! str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $bytes = (string) $response->body();
        $sizeBytes = strlen($bytes);

        if ($sizeBytes <= 0) {
            return null;
        }

        $maxSizeBytes = (int) config('logo.max_size_bytes', 1024 * 1024);

        if ($sizeBytes > $maxSizeBytes) {
            return null;
        }

        return [
            'logo_url' => $iconUrl,
            'bytes' => $bytes,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'extension' => $this->extensionFromMime($mimeType),
        ];
    }

    private function baseUrlFromFeedUrl(string $feedUrl): ?string
    {
        $parts = parse_url($feedUrl);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! is_string($scheme) || $scheme === '' || ! is_string($host) || $host === '') {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return sprintf('%s://%s%s/', $scheme, $host, $port);
    }

    private function toAbsoluteUrl(string $baseUrl, string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        if (str_starts_with($href, '//')) {
            return sprintf('%s:%s', $scheme, $href);
        }

        if (str_starts_with($href, '/')) {
            return sprintf('%s://%s%s%s', $scheme, $host, $port, $href);
        }

        return sprintf('%s://%s%s/%s', $scheme, $host, $port, ltrim($href, '/'));
    }

    private function extractAttribute(string $tag, string $attribute): ?string
    {
        $pattern = sprintf('/\b%s\s*=\s*(["\'])(.*?)\1/i', preg_quote($attribute, '/'));

        if (! preg_match($pattern, $tag, $matches)) {
            return null;
        }

        return html_entity_decode((string) ($matches[2] ?? ''), ENT_QUOTES | ENT_HTML5);
    }

    private function extensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            default => 'bin',
        };
    }
}
