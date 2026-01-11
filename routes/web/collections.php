<?php

use App\Http\Controllers\CollectionController;
use App\Models\Collection;
use Illuminate\Support\Facades\Route;

// Custom route binding for collections that bypasses the user global scope
// This allows accessing shared collections; authorization is handled by CollectionPolicy
Route::bind('collection', function ($value) {
    return Collection::withoutGlobalScope('user')->findOrFail($value);
});

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::prefix('collections')->name('collections.')->group(function () {
        // Static routes first
        Route::get('/', [CollectionController::class, 'index'])->name('index');
        Route::get('/all', [CollectionController::class, 'all'])->name('all');
        Route::get('/shared', [CollectionController::class, 'shared'])->name('shared');
        Route::post('/', [CollectionController::class, 'store'])->name('store');

        // Dynamic routes
        Route::get('/{collection}', [CollectionController::class, 'show'])->name('show');
        Route::patch('/{collection}', [CollectionController::class, 'update'])->name('update');
        Route::delete('/{collection}', [CollectionController::class, 'destroy'])->name('destroy');

        // Archive actions
        Route::post('/{collection}/archive', [CollectionController::class, 'archive'])->name('archive');
        Route::post('/{collection}/unarchive', [CollectionController::class, 'unarchive'])->name('unarchive');

        // File management
        Route::post('/{collection}/files', [CollectionController::class, 'addFiles'])->name('files.add');
        Route::delete('/{collection}/files', [CollectionController::class, 'removeFiles'])->name('files.remove');

        // Sharing
        Route::post('/{collection}/share', [CollectionController::class, 'share'])->name('share');
        Route::delete('/{collection}/share/{user}', [CollectionController::class, 'unshare'])->name('unshare');
    });
});
