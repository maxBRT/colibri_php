<?php

use App\Jobs\GenerateDescriptionsJob;
use App\Models\Post;
use App\Models\Source;
use App\Services\EnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('failed queued jobs are visible in failed_jobs with queue metadata', function () {
    Config::set('queue.default', 'database');

    $this->mock(EnrichmentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('generateSummary')
            ->andThrow(new Exception('Enrichment failed from test'));
    });

    $source = Source::factory()->create();
    $post = Post::factory()->create([
        'source_id' => $source->id,
        'status' => 'processing',
        'description' => null,
    ]);

    $job = new GenerateDescriptionsJob;
    $job->backoff = []; // Remove backoff delays for testing
    dispatch($job);

    // Run worker multiple times to exhaust all retries (job has $tries = 3)
    for ($i = 0; $i < 3; $i++) {
        $this->artisan('queue:work', [
            '--once' => true,
            '--queue' => 'enrichment',
        ]);
    }

    $this->assertDatabaseHas('failed_jobs', [
        'queue' => 'enrichment',
    ]);

    $failedJob = DB::table('failed_jobs')->first();
    expect($failedJob->payload)->toContain('GenerateDescriptionsJob');
});
