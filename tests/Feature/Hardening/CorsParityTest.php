<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$endpoints = ['/v1/categories', '/v1/sources', '/v1/posts', '/health'];

it('api endpoints expose cors headers with parity', function (string $endpoint) {
    $response = $this->getJson($endpoint, [
        'Origin' => 'https://example-client.test',
    ]);

    $response->assertHeader('Access-Control-Allow-Origin', '*');
})->with($endpoints);

it('preflight options request succeeds for read api routes', function (string $endpoint) {
    $response = $this->optionsJson($endpoint, [], [
        'Origin' => 'https://example-client.test',
        'Access-Control-Request-Method' => 'GET',
    ]);

    $response->assertStatus(204)
        ->assertHeader('Access-Control-Allow-Methods')
        ->assertHeader('Access-Control-Allow-Origin', '*');

    expect($response->headers->get('Access-Control-Allow-Methods'))->toContain('GET');
})->with($endpoints);
