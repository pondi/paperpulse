<?php

use App\Http\Controllers\Files\FileManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::prefix('files-processing')->name('files.')->group(function () {
        Route::get('/', [FileManagementController::class, 'index'])->name('index');
        Route::post('/{file}/reprocess', [FileManagementController::class, 'reprocess'])->name('reprocess');
        Route::patch('/{file}/type', [FileManagementController::class, 'changeTypeAndReprocess'])->name('change-type');
    });
});
