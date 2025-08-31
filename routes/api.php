<?php

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// PulseDav Authentication
Route::post('/webdav/auth', [\App\Http\Controllers\Api\WebDavAuthController::class, 'authenticate'])
    ->middleware('throttle:pulsedav-auth');

// File Sharing API
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/documents/{document}/shares', [\App\Http\Controllers\DocumentController::class, 'getShares']);
    Route::get('/receipts/{receipt}/shares', [\App\Http\Controllers\ReceiptController::class, 'getShares']);
});

// Batch Processing API
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('batch')->group(function () {
        Route::post('/', [\App\Http\Controllers\BatchProcessingController::class, 'create']);
        Route::get('/', [\App\Http\Controllers\BatchProcessingController::class, 'list']);
        Route::get('/{batchJob}/status', [\App\Http\Controllers\BatchProcessingController::class, 'status']);
        Route::post('/{batchJob}/cancel', [\App\Http\Controllers\BatchProcessingController::class, 'cancel']);
    });
});
