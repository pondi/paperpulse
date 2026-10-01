<?php

use App\Http\Controllers\Api\WebDavAuthController;
use App\Http\Controllers\BatchProcessingController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Receipts\ReceiptController;
use Illuminate\Http\Request;
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

Route::get('health', HealthController::class);

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
