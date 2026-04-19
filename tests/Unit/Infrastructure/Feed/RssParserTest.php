<?php

use App\Infrastructure\Feed\RssParser;
use Carbon\Exceptions\Exception;
use Illuminate\Support\Carbon;

test('it normalizes rss entries into post payloads', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Example Feed</title>
    <item>
      <title>  First item  </title>
      <link>https://example.test/posts/first</link>
      <guid>first-guid</guid>
      <pubDate>Sat, 19 Apr 2026 12:00:00 GMT</pubDate>
      <description>  First description  </description>
    </item>
    <item>
      <title>Second item</title>
      <link>https://example.test/posts/second</link>
      <guid>second-guid</guid>
      <pubDate>Sat, 19 Apr 2026 13:00:00 GMT</pubDate>
      <description><![CDATA[Second description]]></description>
    </item>
  </channel>
</rss>
XML;

    $entries = (new RssParser)->parse($xml);

    expect($entries)->toHaveCount(2)
        ->and($entries[0])->toMatchArray([
            'title' => 'First item',
            'link' => 'https://example.test/posts/first',
            'guid' => 'first-guid',
        ])
        ->and($entries[0])->not->toHaveKey('description')
        ->and($entries[1])->toMatchArray([
            'title' => 'Second item',
            'link' => 'https://example.test/posts/second',
            'guid' => 'second-guid',
        ])
        ->and($entries[1])->not->toHaveKey('description')
        ->and(fn () => Carbon::parse($entries[0]['pub_date']))->not->toThrow(Exception::class)
        ->and(fn () => Carbon::parse($entries[1]['pub_date']))->not->toThrow(Exception::class);
});

test('it skips malformed rss entries instead of failing full feed', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Example Feed</title>
    <item>
      <title>Valid item</title>
      <link>https://example.test/posts/valid</link>
      <guid>valid-guid</guid>
      <pubDate>Sat, 19 Apr 2026 12:00:00 GMT</pubDate>
      <description>Valid description</description>
    </item>
    <item>
      <title>No link</title>
      <guid>missing-link-guid</guid>
      <pubDate>Sat, 19 Apr 2026 13:00:00 GMT</pubDate>
      <description>Missing link should skip</description>
    </item>
    <item>
      <title>No publication date</title>
      <link>https://example.test/posts/missing-date</link>
      <guid>missing-date-guid</guid>
      <description>Missing pubDate should skip</description>
    </item>
    <item>
      <link>https://example.test/posts/missing-title</link>
      <guid>missing-title-guid</guid>
      <pubDate>Sat, 19 Apr 2026 15:00:00 GMT</pubDate>
      <description>Missing title should skip</description>
    </item>
  </channel>
</rss>
XML;

    $entries = (new RssParser)->parse($xml);

    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toMatchArray([
            'title' => 'Valid item',
            'link' => 'https://example.test/posts/valid',
            'guid' => 'valid-guid',
        ])
        ->and($entries[0])->not->toHaveKey('description');
});
