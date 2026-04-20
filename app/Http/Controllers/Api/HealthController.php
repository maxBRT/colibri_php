<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        try {
            DB::select('select 1');

            return response()->json([
                'status' => 'ok',
                'checks' => [
                    'app' => 'ok',
                    'database' => 'ok',
                ],
            ]);
        } catch (Throwable) {
            return response()->json([
                'status' => 'degraded',
                'checks' => [
                    'app' => 'ok',
                    'database' => 'error',
                ],
            ], 503);
        }
    }
}
