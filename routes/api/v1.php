<?php

use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Protected API routes
Route::middleware(['auth:sanctum', 'api.rate_limit:200,1'])->group(function () {
    
    // Documents
    Route::apiResource('documents', DocumentController::class);
    Route::post('documents/{document}/share', [DocumentController::class, 'share']);
    Route::delete('documents/{document}/share/{user}', [DocumentController::class, 'unshare']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
});