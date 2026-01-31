<?php

use App\Http\Controllers\DuplicateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::prefix('duplicates')->name('duplicates.')->group(function () {
        Route::get('/', [DuplicateController::class, 'index'])->name('index');
        Route::post('/{duplicateFlag}/resolve', [DuplicateController::class, 'resolve'])->name('resolve');
        Route::post('/{duplicateFlag}/ignore', [DuplicateController::class, 'ignore'])->name('ignore');
    });
});
