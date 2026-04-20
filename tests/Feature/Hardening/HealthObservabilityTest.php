<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('health endpoint reports app and database connectivity checks', function () {
    $response = $this->getJson('/health');

    $response->assertSuccessful()
        ->assertJsonPath('checks.app', 'ok')
        ->assertJsonPath('checks.database', 'ok');
});
