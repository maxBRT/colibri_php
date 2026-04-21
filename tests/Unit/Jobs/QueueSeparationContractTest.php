<?php

use App\Jobs\CleanUpOutdatedPostJob;
use App\Jobs\FetchRssJob;
use App\Jobs\GenerateDescriptionsJob;
use App\Jobs\SyncLogosJob;

it('asserts jobs use correct queues', function () {
    expect((new FetchRssJob([]))->queue ?? 'rss')->toBe('rss')
        ->and((new GenerateDescriptionsJob)->queue ?? 'enrichment')->toBe('enrichment')
        ->and((new SyncLogosJob)->queue ?? 'logos')->toBe('logos')
        ->and((new CleanUpOutdatedPostJob)->queue ?? 'default')->toBe('cleanup');
});
