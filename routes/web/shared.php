<?php

use App\Http\Controllers\Public\PublicCollectionController;
use Illuminate\Support\Facades\Route;

// Public shared collection routes — no auth required, session-based for password unlock
Route::prefix('shared')->name('shared.')->middleware(['web', 'throttle:60,1'])->group(function () {
    Route::get('/collections/{token}', [PublicCollectionController::class, 'show'])->name('collections.show');

    Route::post('/collections/{token}/verify', [PublicCollectionController::class, 'verifyPassword'])
        ->middleware('throttle:5,1')
        ->name('collections.verify');

    Route::get('/collections/{token}/files/{guid}', [PublicCollectionController::class, 'serveFile'])
        ->name('collections.file');

    Route::get('/collections/{token}/download', [PublicCollectionController::class, 'downloadAll'])
        ->middleware('throttle:5,1')
        ->name('collections.download');
});
