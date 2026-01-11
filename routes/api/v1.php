<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\FileContentController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\SearchController;
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

    // Collections
    Route::prefix('collections')->name('api.collections.')->group(function () {
        Route::get('/', [CollectionController::class, 'index'])->name('index');
        Route::get('/all', [CollectionController::class, 'all'])->name('all');
        Route::get('/shared', [CollectionController::class, 'shared'])->name('shared');
        Route::post('/', [CollectionController::class, 'store'])->name('store');
        Route::get('/{id}', [CollectionController::class, 'show'])->name('show');
        Route::patch('/{id}', [CollectionController::class, 'update'])->name('update');
        Route::delete('/{id}', [CollectionController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/archive', [CollectionController::class, 'archive'])->name('archive');
        Route::post('/{id}/unarchive', [CollectionController::class, 'unarchive'])->name('unarchive');
        Route::post('/{id}/files', [CollectionController::class, 'addFiles'])->name('files.add');
        Route::delete('/{id}/files', [CollectionController::class, 'removeFiles'])->name('files.remove');
        Route::get('/{id}/shares', [CollectionController::class, 'shares'])->name('shares');
        Route::post('/{id}/share', [CollectionController::class, 'share'])->name('share');
        Route::delete('/{id}/share/{userId}', [CollectionController::class, 'unshare'])->name('unshare');
    });
});
