<?php

use App\Jobs\SyncLogosJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('sync logos command dispatches logos job', function () {
    Queue::fake();

    $this->artisan('sync:logos')
        ->expectsOutput('Logo sync job dispatched.')
        ->assertSuccessful();

    Queue::assertPushed(SyncLogosJob::class);
    Queue::assertPushedOn('logos', SyncLogosJob::class);
});
