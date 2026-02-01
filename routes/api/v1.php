<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\FileContentController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\TagController;
use Illuminate\Support\Facades\Route;

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Protected API routes
Route::middleware(['auth:sanctum', 'api.rate_limit:200,1'])->group(function () {
    // File upload & listing (single file upload only)
    Route::get('files', [FileController::class, 'index'])->name('api.files.index');
    Route::get('files/{file}', [FileController::class, 'show'])->name('api.files.show');
    Route::get('files/{file}/content', [FileContentController::class, 'show'])->name('api.files.content');
    Route::post('files', [FileController::class, 'store'])->name('api.files.store');

    // Search
    Route::get('search', [SearchController::class, 'index'])->name('api.search');

    // Tags
    Route::get('tags', [TagController::class, 'index'])->name('api.tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('api.tags.store');
    Route::patch('tags/{tag}', [TagController::class, 'update'])->name('api.tags.update');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('api.tags.destroy');

    // Collections
    Route::get('collections', [CollectionController::class, 'index'])->name('api.collections.index');
    Route::post('collections', [CollectionController::class, 'store'])->name('api.collections.store');
    Route::patch('collections/{collection}', [CollectionController::class, 'update'])->name('api.collections.update');
    Route::delete('collections/{collection}', [CollectionController::class, 'destroy'])->name('api.collections.destroy');
});
