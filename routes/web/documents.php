<?php

use App\Http\Controllers\Documents\DocumentBulkController;
use App\Http\Controllers\Documents\DocumentController;
// Use DocumentController for share/unshare via ShareableController trait
use App\Http\Controllers\Files\FileProcessingController;
use App\Http\Controllers\Files\FileServeController;
use App\Http\Middleware\CheckBetaFeatures;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web', CheckBetaFeatures::class.':documents'])->group(function () {
    // Document routes (protected by beta feature flag)
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::get('/upload', [DocumentController::class, 'upload'])->name('upload');
        Route::get('/shared', [DocumentController::class, 'shared'])->name('shared');
        Route::get('/categories', [DocumentController::class, 'categories'])->name('categories');
        Route::post('/store', [FileProcessingController::class, 'store'])->name('store');
        Route::delete('/bulk', [DocumentBulkController::class, 'destroyBulk'])->name('destroy-bulk');
        Route::get('/bulk/download', [DocumentBulkController::class, 'downloadBulk'])->name('download-bulk');
        Route::get('/serve', [FileServeController::class, 'serve'])->name('serve');

        // Dynamic routes (must be after static routes)
        Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
        Route::patch('/{document}', [DocumentController::class, 'update'])->name('update');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
        Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
        Route::post('/{document}/share', [DocumentController::class, 'share'])->name('share');
        Route::delete('/{document}/share/{user}', [DocumentController::class, 'unshare'])->name('unshare');
        Route::post('/{document}/tags', [DocumentController::class, 'attachTag'])->name('tags.store');
        Route::delete('/{document}/tags/{tag}', [DocumentController::class, 'detachTag'])->name('tags.destroy');
    });
});
