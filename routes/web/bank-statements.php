<?php

use App\Http\Controllers\BankStatementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::prefix('bank-statements')->name('bank-statements.')->group(function () {
        Route::get('/', [BankStatementController::class, 'index'])->name('index');
        Route::get('/{bankStatement}', [BankStatementController::class, 'show'])->name('show');
        Route::patch('/{bankStatement}', [BankStatementController::class, 'update'])->name('update');
        Route::delete('/{bankStatement}', [BankStatementController::class, 'destroy'])->name('destroy');
        Route::get('/{bankStatement}/download', [BankStatementController::class, 'download'])->name('download');
        Route::post('/{bankStatement}/tags', [BankStatementController::class, 'attachTag'])->name('tags.store');
        Route::delete('/{bankStatement}/tags/{tag}', [BankStatementController::class, 'detachTag'])->name('tags.destroy');
        Route::get('/{bankStatement}/transactions', [BankStatementController::class, 'transactions'])->name('transactions');
    });
});
