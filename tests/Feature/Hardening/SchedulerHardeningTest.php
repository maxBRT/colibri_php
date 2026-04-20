<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scheduler runs job chain every four hours with overlap and single-server guards', function () {
    $schedule = app(Schedule::class);

    // Find the event that runs every 4 hours (the chain)
    $events = collect($schedule->events())->filter(function ($event) {
        return $event->expression === '0 */4 * * *';
    });

    expect($events)->toHaveCount(1);

    $event = $events->first();
    expect($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('scheduler runs cleanup posts daily with overlap guard', function () {
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())->filter(function ($event) {
        return str_contains($event->command ?? $event->description, 'CleanUpOutdatedPostJob') ||
               str_contains($event->command ?? $event->description, 'CleanupPostsJob');
    });

    expect($events)->not->toBeEmpty();

    $event = $events->first();
    expect($event->expression)->toBe('0 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
