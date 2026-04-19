<?php

use App\Jobs\GenerateDescriptionsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('posts describe command dispatches job with default limit', function () {
    Queue::fake();

    $this->artisan('posts:describe')
        ->assertSuccessful();

    Queue::assertPushed(GenerateDescriptionsJob::class, function (GenerateDescriptionsJob $job) {
        return $job->limit === null;
    });
});

test('posts describe command dispatches job with custom limit', function () {
    Queue::fake();

    $this->artisan('posts:describe --limit=12')
        ->assertSuccessful();

    Queue::assertPushed(GenerateDescriptionsJob::class, function (GenerateDescriptionsJob $job) {
        return $job->limit === 12;
    });
});

test('posts describe command dispatches job to enrichment queue', function () {
    Queue::fake();

    $this->artisan('posts:describe --limit=2')
        ->assertSuccessful();

    Queue::assertPushedOn('enrichment', GenerateDescriptionsJob::class);
});

test('posts describe command persists queued job to database queue', function () {
    config()->set('queue.default', 'database');

    $this->assertDatabaseCount('jobs', 0);

    $this->artisan('posts:describe --limit=3')
        ->assertSuccessful();

    $this->assertDatabaseHas('jobs', [
        'queue' => 'enrichment',
    ]);

    $payload = (string) DB::table('jobs')->latest('id')->value('payload');

    expect($payload)->toContain('GenerateDescriptionsJob');
});
