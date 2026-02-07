<?php

use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::prefix('vouchers')->name('vouchers.')->group(function () {
        Route::get('/', [VoucherController::class, 'index'])->name('index');
        Route::get('/{voucher}', [VoucherController::class, 'show'])->name('show');
        Route::post('/{voucher}/redeem', [VoucherController::class, 'redeem'])->name('redeem');
        Route::delete('/{voucher}', [VoucherController::class, 'destroy'])->name('destroy');
        Route::get('/{voucher}/download', [VoucherController::class, 'download'])->name('download');
        Route::post('/{voucher}/tags', [VoucherController::class, 'attachTag'])->name('tags.store');
        Route::delete('/{voucher}/tags/{tag}', [VoucherController::class, 'detachTag'])->name('tags.destroy');
    });
});
