<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
        Route::patch('/{invoice}', [InvoiceController::class, 'update'])->name('update');
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->name('destroy');
        Route::get('/{invoice}/download', [InvoiceController::class, 'download'])->name('download');
        Route::post('/{invoice}/tags', [InvoiceController::class, 'attachTag'])->name('tags.store');
        Route::delete('/{invoice}/tags/{tag}', [InvoiceController::class, 'detachTag'])->name('tags.destroy');
    });
});
