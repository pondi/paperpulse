<?php

use App\Http\Controllers\Api\WebDavAuthController;
use App\Http\Controllers\BatchProcessingController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Receipts\ReceiptController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check
Route::get('health', function () {
    $components = [];
    $hasCriticalFailure = false;
    $hasFailure = false;

    // Database check
    try {
        $start = microtime(true);
        DB::select('SELECT 1');
        $components['database'] = [
            'status' => 'ok',
            'latency_ms' => round((microtime(true) - $start) * 1000),
        ];
    } catch (Throwable $e) {
        $components['database'] = ['status' => 'down'];
        $hasCriticalFailure = true;
    }

    // Redis check
    try {
        $start = microtime(true);
        Redis::ping();
        $components['redis'] = [
            'status' => 'ok',
            'latency_ms' => round((microtime(true) - $start) * 1000),
        ];
    } catch (Throwable $e) {
        $components['redis'] = ['status' => 'down'];
        $hasCriticalFailure = true;
    }

    // Meilisearch check
    try {
        $host = config('scout.meilisearch.host');
        if ($host) {
            $start = microtime(true);
            $response = Http::timeout(2)->get($host.'/health');
            $components['meilisearch'] = [
                'status' => $response->successful() ? 'ok' : 'down',
                'latency_ms' => round((microtime(true) - $start) * 1000),
            ];
            if (! $response->successful()) {
                $hasFailure = true;
            }
        }
    } catch (Throwable $e) {
        $components['meilisearch'] = ['status' => 'down'];
        $hasFailure = true;
    }

    // Queue depth check
    try {
        $depth = Queue::size('default');
        $components['queue'] = [
            'status' => 'ok',
            'depth' => $depth,
        ];
    } catch (Throwable $e) {
        $components['queue'] = ['status' => 'down'];
        $hasFailure = true;
    }

    $overallStatus = $hasCriticalFailure ? 'down' : ($hasFailure ? 'degraded' : 'ok');
    $httpStatus = $hasCriticalFailure ? 503 : 200;

    return response()->json([
        'status' => $overallStatus,
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
        'components' => $components,
    ], $httpStatus);
});

// V1 API routes
Route::prefix('v1')
    ->middleware(['api.version:v1'])
    ->group(base_path('routes/api/v1.php'));

// Legacy user endpoint for backward compatibility
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// PulseDav Authentication
Route::post('/webdav/auth', [WebDavAuthController::class, 'authenticate'])
    ->middleware('throttle:pulsedav-auth');

// File Sharing API
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/documents/{document}/shares', [DocumentController::class, 'getShares']);
    Route::get('/receipts/{receipt}/shares', [ReceiptController::class, 'getShares']);
});

// Batch Processing API
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('batch')->group(function () {
        Route::post('/', [BatchProcessingController::class, 'create']);
        Route::get('/', [BatchProcessingController::class, 'list']);
        Route::get('/{batchJob}/status', [BatchProcessingController::class, 'status']);
        Route::post('/{batchJob}/cancel', [BatchProcessingController::class, 'cancel']);
    });
});
