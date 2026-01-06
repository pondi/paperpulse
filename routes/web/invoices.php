<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
    });
});
