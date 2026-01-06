<?php

use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::prefix('vouchers')->name('vouchers.')->group(function () {
        Route::get('/', [VoucherController::class, 'index'])->name('index');
        Route::get('/{voucher}', [VoucherController::class, 'show'])->name('show');
        Route::post('/{voucher}/redeem', [VoucherController::class, 'redeem'])->name('redeem');
    });
});
