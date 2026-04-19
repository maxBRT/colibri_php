<?php

namespace App\Services;

use App\Infrastructure\Feed\RssParser;
use Illuminate\Support\Facades\Http;

class RssService
{
    public function __construct(private readonly RssParser $parser) {}

    /**
     * @return array<int, array{title: string, link: string, guid: string, pub_date: string}>
     */
    public function fetchAndParse(string $url): array
    {
        $response = Http::connectTimeout((int) config('rss.connect_timeout'))
            ->timeout((int) config('rss.timeout'))
            ->retry((int) config('rss.retry_times'), (int) config('rss.retry_sleep_ms'))
            ->withUserAgent((string) config('rss.user_agent'))
            ->accept('application/rss+xml, application/atom+xml, application/xml, text/xml')
            ->get($url);

        if ($response->failed()) {
            return [];
        }

        return $this->parser->parse($response->body());
    }
}
