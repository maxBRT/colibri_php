<?php

namespace App\Infrastructure\Feed;

use Illuminate\Support\Carbon;

class RssParser
{
    /**
     * @return array<int, array{title: string, link: string, guid: string, pub_date: string}>
     */
    public function parse(string $xml): array
    {
        $feed = @simplexml_load_string($xml, options: LIBXML_NOCDATA);

        if ($feed === false) {
            return [];
        }

        $items = [];

        if (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $normalized = $this->normalizeRssItem($item);

                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }
        }

        if (isset($feed->entry)) {
            foreach ($feed->entry as $entry) {
                $normalized = $this->normalizeAtomEntry($entry);

                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }
        }

        return $items;
    }

    /**
     * @return array{title: string, link: string, guid: string, pub_date: string}|null
     */
    private function normalizeRssItem(\SimpleXMLElement $item): ?array
    {
        $title = trim((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));
        $guid = trim((string) ($item->guid ?? ''));
        $pubDate = trim((string) ($item->pubDate ?? ''));

        if ($title === '' || $link === '' || $guid === '' || $pubDate === '') {
            return null;
        }

        try {
            $publishedAt = Carbon::parse($pubDate)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }

        return [
            'title' => $title,
            'link' => $link,
            'guid' => $guid,
            'pub_date' => $publishedAt,
        ];
    }

    /**
     * @return array{title: string, link: string, guid: string, pub_date: string}|null
     */
    private function normalizeAtomEntry(\SimpleXMLElement $entry): ?array
    {
        $title = trim((string) ($entry->title ?? ''));
        $id = trim((string) ($entry->id ?? ''));
        $updated = trim((string) ($entry->updated ?? ''));
        $published = trim((string) ($entry->published ?? ''));
        $pubDate = $published !== '' ? $published : $updated;

        $link = '';

        if (isset($entry->link)) {
            foreach ($entry->link as $atomLink) {
                $href = trim((string) $atomLink['href']);

                if ($href !== '') {
                    $link = $href;

                    break;
                }
            }
        }

        $guid = $id !== '' ? $id : $link;

        if ($title === '' || $link === '' || $guid === '' || $pubDate === '') {
            return null;
        }

        try {
            $publishedAt = Carbon::parse($pubDate)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }

        return [
            'title' => $title,
            'link' => $link,
            'guid' => $guid,
            'pub_date' => $publishedAt,
        ];
    }
}
