<?php

use App\Models\Post;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('api endpoints enforce baseline rate limit of 100 requests per minute per ip', function () {
    // Setup minimum data to ensure 200s (if missing)
    $source = Source::factory()->create();
    Post::factory()->create(['source_id' => $source->id]);

    for ($i = 0; $i < 100; $i++) {
        $this->getJson('/v1/categories')->assertSuccessful();
    }

    $response = $this->getJson('/v1/categories');

    $response->assertStatus(429)
        ->assertJsonStructure(['error' => ['code', 'message']])
        ->assertJsonPath('error.code', 'rate_limited')
        ->assertHeader('Retry-After');
});

it('rate limiting is isolated by client ip', function () {
    for ($i = 0; $i < 100; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->getJson('/v1/categories');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->getJson('/v1/categories')->assertStatus(429);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->getJson('/v1/categories')->assertSuccessful();
});

it('all read endpoints share same throttle behavior and envelope', function (string $endpoint) {
    for ($i = 0; $i < 100; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->getJson($endpoint);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->getJson($endpoint)
        ->assertStatus(429)
        ->assertJsonStructure(['error' => ['code', 'message']])
        ->assertJsonPath('error.code', 'rate_limited')
        ->assertHeader('Retry-After');
})->with(['/v1/categories', '/v1/sources', '/v1/posts', '/health']);
