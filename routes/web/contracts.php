<?php

use App\Http\Controllers\ContractController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::prefix('contracts')->name('contracts.')->group(function () {
        Route::get('/', [ContractController::class, 'index'])->name('index');
        Route::get('/{contract}', [ContractController::class, 'show'])->name('show');
        Route::patch('/{contract}', [ContractController::class, 'update'])->name('update');
        Route::delete('/{contract}', [ContractController::class, 'destroy'])->name('destroy');
        Route::get('/{contract}/download', [ContractController::class, 'download'])->name('download');
        Route::post('/{contract}/tags', [ContractController::class, 'attachTag'])->name('tags.store');
        Route::delete('/{contract}/tags/{tag}', [ContractController::class, 'detachTag'])->name('tags.destroy');
    });
});
