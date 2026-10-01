<?php

namespace App\Http\Controllers;

use App\Services\ReadinessCheck;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(ReadinessCheck $readiness): JsonResponse
    {
        $result = $readiness->check();

        return response()->json([
            ...$result,
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
        ], $result['status'] === 'ok' ? 200 : 503);
    }
}
