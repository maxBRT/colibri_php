<?php

use App\Jobs\CleanUpOutdatedPostJob;
use App\Jobs\FetchRssJob;
use App\Jobs\GenerateDescriptionsJob;
use App\Jobs\SyncLogosJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Bus::chain([
        new FetchRssJob,
        new GenerateDescriptionsJob,
        new SyncLogosJob,
    ])->dispatch();
})->name('rss-processing-chain')
    ->everyFourHours()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new CleanUpOutdatedPostJob)
    ->daily()
    ->withoutOverlapping();
