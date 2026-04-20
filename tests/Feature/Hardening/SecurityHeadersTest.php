<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$endpoints = ['/v1/categories', '/v1/sources', '/v1/posts', '/health'];

it('api endpoints expose required security headers with parity', function (string $endpoint) {
    $response = $this->getJson($endpoint);

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options')
        ->assertHeader('Referrer-Policy');
})->with($endpoints);
