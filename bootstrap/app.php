<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle Validation Errors (422)
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'validation_error',
                        'message' => $e->getMessage(),
                    ],
                ], 422);
            }
        });

        // Handle Not Found Errors (404)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'not_found',
                        'message' => 'The requested resource was not found.',
                    ],
                ], 404);
            }
        });

        // Handle Method Not Allowed Errors (405)
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'method_not_allowed',
                        'message' => 'The HTTP method is not allowed for this endpoint.',
                    ],
                ], 405);
            }
        });

        // Handle Rate Limiting (429)
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'rate_limited',
                        'message' => 'Too many requests. Please try again later.',
                    ],
                ], 429, ['Retry-After' => $e->getHeaders()['Retry-After'] ?? 60]);
            }
        });

        // Handle Malformed JSON / Bad Request (400)
        $exceptions->render(function (JsonException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'bad_request',
                        'message' => 'Invalid JSON provided in the request body.',
                    ],
                ], 400);
            }
        });

        // Handle Database Query Errors (500)
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'database_error',
                        'message' => 'A database error occurred.',
                    ],
                ], 500);
            }
        });

        // Handle All Other Exceptions (500) - catch-all
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                // Skip if this is one of the already handled exception types
                if ($e instanceof ValidationException ||
                    $e instanceof NotFoundHttpException ||
                    $e instanceof MethodNotAllowedHttpException ||
                    $e instanceof ThrottleRequestsException ||
                    $e instanceof JsonException ||
                    $e instanceof QueryException) {
                    return null;
                }

                return response()->json([
                    'error' => [
                        'code' => 'server_error',
                        'message' => 'An unexpected error occurred.',
                    ],
                ], 500);
            }
        });
    })->create();
