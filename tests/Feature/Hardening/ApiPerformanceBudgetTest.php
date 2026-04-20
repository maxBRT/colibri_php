<?php

use App\Models\Logo;
use App\Models\Post;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('GET v1 posts stays within query budget for page reads', function () {
    $sources = Source::factory(3)->create();
    foreach ($sources as $source) {
        Post::factory(20)->create(['source_id' => $source->id]);
        Logo::factory()->create(['source_id' => $source->id]);
    }

    DB::enableQueryLog();

    $response = $this->getJson('/v1/posts?page=1&per_page=20');

    $response->assertSuccessful();

    $queryCount = count(DB::getQueryLog());

    expect($queryCount)->toBeLessThanOrEqual(3);
});

it('GET v1 sources stays within strict query budget without n plus one', function () {
    $sources = Source::factory(10)->create();
    foreach ($sources as $source) {
        Logo::factory()->create(['source_id' => $source->id]);
    }

    DB::enableQueryLog();

    $response = $this->getJson('/v1/sources');

    $response->assertSuccessful();

    $queryCount = count(DB::getQueryLog());

    expect($queryCount)->toBeLessThanOrEqual(1);
});
