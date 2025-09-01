<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Jobs routes (admin only)
    Route::prefix('jobs')->name('jobs.')->middleware('can:admin')->group(function () {
        Route::get('/', [JobController::class, 'index'])->name('index');
        Route::get('/status', [JobController::class, 'getStatus'])->name('status');
        Route::get('/{jobId}', [JobController::class, 'show'])->name('show');
        Route::post('/{jobId}/restart', [JobController::class, 'restart'])->name('restart');
        Route::post('/restart-multiple', [JobController::class, 'restartMultiple'])->name('restart-multiple');
    });

    // Merchant routes
    Route::prefix('merchants')->name('merchants.')->group(function () {
        Route::get('/', [MerchantController::class, 'index'])->name('index');
        Route::post('/{merchant}/logo', [MerchantController::class, 'updateLogo'])->name('updateLogo');
    });

    // Vendor routes
    Route::prefix('vendors')->name('vendors.')->group(function () {
        Route::get('/', [VendorController::class, 'index'])->name('index');
        Route::get('/{vendor}', [VendorController::class, 'show'])->name('show');
        Route::post('/{vendor}/logo', [VendorController::class, 'updateLogo'])->name('updateLogo');
    });

    // Export routes
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('/receipts/csv', [ExportController::class, 'exportCsv'])->name('receipts.csv');
        Route::get('/receipts/pdf', [ExportController::class, 'exportPdf'])->name('receipts.pdf');
        Route::get('/receipt/{id}/pdf', [ExportController::class, 'exportReceiptPdf'])->name('receipt.pdf');
    });

    // Notification routes
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
        Route::post('/clear', [NotificationController::class, 'clear'])->name('clear');
    });
});
