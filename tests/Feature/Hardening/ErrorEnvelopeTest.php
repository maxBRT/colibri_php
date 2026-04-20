<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

describe('Error Envelope Handling', function () {

    describe('Validation Errors', function () {
        it('returns standard error envelope for validation failures', function () {
            $response = $this->getJson('/v1/posts?per_page=101');

            $response->assertStatus(422)
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'validation_error');
        });

        it('returns validation error for invalid per_page parameter', function () {
            $response = $this->getJson('/v1/posts?per_page=invalid');

            $response->assertStatus(422)
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'validation_error');
        });

        it('returns validation error for invalid source parameter', function () {
            $response = $this->getJson('/v1/posts?sources[]=invalid-source-id');

            $response->assertStatus(422)
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'validation_error');
        });
    });

    describe('Not Found Errors (404)', function () {
        it('returns error envelope for non-existent API routes', function () {
            $response = $this->getJson('/v1/non-existent-resource');

            $response->assertNotFound()
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'not_found');
        });

        it('returns error envelope for non-existent resource with trailing slash', function () {
            $response = $this->getJson('/v1/unknown-endpoint/');

            $response->assertNotFound()
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'not_found');
        });

        it('returns error envelope for deeply nested non-existent paths', function () {
            $response = $this->getJson('/v1/posts/99999/comments/invalid');

            $response->assertNotFound()
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'not_found');
        });
    });

    describe('Method Not Allowed Errors (405)', function () {
        it('returns error envelope for unsupported HTTP methods', function () {
            $response = $this->postJson('/health', []);

            $response->assertStatus(405)
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'method_not_allowed');
        });

        it('returns error envelope for PUT request on read-only endpoint', function () {
            $response = $this->putJson('/v1/posts', []);

            $response->assertStatus(405)
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'method_not_allowed');
        });

        it('returns error envelope for DELETE request on read-only endpoint', function () {
            $response = $this->deleteJson('/v1/categories');

            $response->assertStatus(405)
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'method_not_allowed');
        });

        it('returns error envelope for PATCH request on read-only endpoint', function () {
            $response = $this->patchJson('/v1/sources', []);

            $response->assertStatus(405)
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'method_not_allowed');
        });
    });

    describe('Server Errors (500)', function () {
        it('returns error envelope for runtime exceptions', function () {
            Route::get('/_test/server-error', function () {
                throw new RuntimeException('Simulated server error');
            });

            $response = $this->getJson('/_test/server-error');

            $response->assertServerError()
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'server_error');
        });

        it('returns error envelope for generic exceptions', function () {
            Route::get('/_test/generic-error', function () {
                throw new Exception('Generic application error');
            });

            $response = $this->getJson('/_test/generic-error');

            $response->assertServerError()
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'server_error');
        });

        it('returns error envelope for errors', function () {
            Route::get('/_test/fatal-error', function () {
                throw new Error('Fatal runtime error');
            });

            $response = $this->getJson('/_test/fatal-error');

            $response->assertServerError()
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'server_error');
        });

        it('returns error envelope for type errors', function () {
            Route::get('/_test/type-error', function () {
                throw new TypeError('Type mismatch error');
            });

            $response = $this->getJson('/_test/type-error');

            $response->assertServerError()
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'server_error');
        });

        it('returns error envelope for query exceptions', function () {
            Route::get('/_test/query-error', function () {
                throw new QueryException(
                    'mysql',
                    'SELECT * FROM non_existent_table',
                    [],
                    new Exception('Table not found')
                );
            });

            $response = $this->getJson('/_test/query-error');

            $response->assertServerError()
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ])
                ->assertJsonPath('error.code', 'database_error');
        });
    });

    describe('Error Envelope Structure Consistency', function () {
        it('always returns error envelope with required keys for 404 errors', function () {
            $response = $this->getJson('/v1/does-not-exist');
            $data = $response->json();

            expect($data)->toHaveKey('error')
                ->and($data['error'])->toHaveKey('code')
                ->and($data['error'])->toHaveKey('message')
                ->and($data['error']['code'])->toBeString()
                ->and($data['error']['message'])->toBeString();
        });

        it('always returns error envelope with required keys for 500 errors', function () {
            Route::get('/_test/structure-check', function () {
                throw new RuntimeException('Structure check');
            });

            $response = $this->getJson('/_test/structure-check');
            $data = $response->json();

            expect($data)->toHaveKey('error')
                ->and($data['error'])->toHaveKey('code')
                ->and($data['error'])->toHaveKey('message')
                ->and($data['error']['code'])->toBeString()
                ->and($data['error']['message'])->toBeString();
        });

        it('error codes are consistent strings without spaces', function () {
            Route::get('/_test/code-format', function () {
                throw new RuntimeException('Test');
            });

            $response = $this->getJson('/_test/code-format');
            $code = $response->json('error.code');

            expect($code)->toMatch('/^[a-z_]+$/');
        });
    });

    describe('Error Envelope for Different Accept Headers', function () {
        it('returns error envelope when Accept header is application/json', function () {
            $response = $this->withHeaders([
                'Accept' => 'application/json',
            ])->get('/v1/non-existent');

            $response->assertNotFound()
                ->assertJsonStructure([
                    'error' => ['code', 'message'],
                ]);
        });

    });
});
