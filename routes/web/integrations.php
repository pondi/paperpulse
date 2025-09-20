<?php

use App\Http\Controllers\PulseDavController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    // PulseDav routes
    Route::prefix('pulsedav')->name('pulsedav.')->group(function () {
        Route::get('/', [PulseDavController::class, 'index'])->name('index');
        Route::post('/sync', [PulseDavController::class, 'sync'])->name('sync');
        Route::post('/sync-folders', [PulseDavController::class, 'syncWithFolders'])->name('sync-folders');
        Route::post('/process', [PulseDavController::class, 'process'])->name('process');
        Route::get('/files/{id}/status', [PulseDavController::class, 'status'])->name('status');
        Route::delete('/files/{id}', [PulseDavController::class, 'destroy'])->name('destroy');

        // Folder support routes
        Route::get('/folders', [PulseDavController::class, 'folders'])->name('folders');
        Route::get('/folder-contents', [PulseDavController::class, 'folderContents'])->name('folder-contents');
        Route::post('/import', [PulseDavController::class, 'importSelections'])->name('import');
        Route::post('/folder-tags', [PulseDavController::class, 'updateFolderTags'])->name('folder-tags');

        // Tag routes
        Route::get('/tags/search', [PulseDavController::class, 'searchTags'])->name('tags.search');
        Route::post('/tags', [PulseDavController::class, 'createTag'])->name('tags.create');
    });

    // Search routes
    Route::get('/search', [SearchController::class, 'search'])->name('search');
});
