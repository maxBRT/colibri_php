<?php

use App\Jobs\FetchRssJob;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('rss fetch command dispatches job for all sources', function () {
    Source::query()->create([
        'id' => 'source-tech',
        'name' => 'Tech Source',
        'url' => 'https://source.test/rss.xml',
        'category' => 'tech',
    ]);

    Queue::fake();

    $this->artisan('rss:fetch')
        ->expectsOutput('RSS fetch job dispatched.')
        ->assertSuccessful();

    Queue::assertPushed(FetchRssJob::class, function (FetchRssJob $job) {
        return $job->sourceIds === null;
    });
});

test('rss fetch command dispatches job with source filter', function () {
    Source::query()->create([
        'id' => 'source-tech',
        'name' => 'Tech Source',
        'url' => 'https://source.test/rss.xml',
        'category' => 'tech',
    ]);

    Queue::fake();

    $this->artisan('rss:fetch --source=source-tech')
        ->expectsOutput('RSS fetch job dispatched.')
        ->assertSuccessful();

    Queue::assertPushed(FetchRssJob::class, function (FetchRssJob $job) {
        return $job->sourceIds === ['source-tech'];
    });
});
