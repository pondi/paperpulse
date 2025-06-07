<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BulkOperationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PulseDavController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\VendorController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Authenticated & Inertia routes
Route::middleware(['auth', 'verified', 'web'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('/categories/order', [CategoryController::class, 'updateOrder'])->name('categories.order');
    Route::post('/categories/defaults', [CategoryController::class, 'createDefaults'])->name('categories.defaults');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Preferences routes
    Route::get('/preferences', [PreferencesController::class, 'index'])->name('preferences.index');
    Route::patch('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
    Route::post('/preferences/reset', [PreferencesController::class, 'reset'])->name('preferences.reset');

    // Merchant routes
    Route::get('/merchants', [MerchantController::class, 'index'])->name('merchants.index');
    Route::post('/merchants/{merchant}/logo', [MerchantController::class, 'updateLogo'])->name('merchants.updateLogo');

    // Vendor routes
    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
    Route::post('/vendors/{vendor}/logo', [VendorController::class, 'updateLogo'])->name('vendors.updateLogo');

    // Document routes
    Route::get('/documents/upload', function () {
        return Inertia::render('Documents/Upload');
    })->name('documents.upload');
    Route::post('/documents/store', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/serve', [DocumentController::class, 'serve'])->name('documents.serve');
    Route::get('/documents/url', [DocumentController::class, 'getSecureUrl'])->name('documents.url');

    // Receipt routes
    Route::get('/receipts', [ReceiptController::class, 'index'])->name('receipts.index');
    Route::get('/receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::get('/receipts/{receipt}/image', [ReceiptController::class, 'showImage'])->name('receipts.showImage');
    Route::get('/receipts/{receipt}/pdf', [ReceiptController::class, 'showPdf'])->name('receipts.showPdf');
    Route::get('/receipts/merchant/{merchant}', [ReceiptController::class, 'byMerchant'])->name('receipts.byMerchant');
    Route::delete('/receipts/{receipt}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');
    Route::patch('/receipts/{receipt}', [ReceiptController::class, 'update'])->name('receipts.update');
    Route::post('/receipts/{receipt}/line-items', [ReceiptController::class, 'addLineItem'])->name('receipts.line-items.store');
    Route::patch('/receipts/{receipt}/line-items/{lineItem}', [ReceiptController::class, 'updateLineItem'])->name('receipts.line-items.update');
    Route::delete('/receipts/{receipt}/line-items/{lineItem}', [ReceiptController::class, 'deleteLineItem'])->name('receipts.line-items.destroy');

    // Bulk operations
    Route::prefix('bulk')->name('bulk.')->group(function () {
        Route::post('/receipts/delete', [BulkOperationController::class, 'bulkDelete'])->name('receipts.delete');
        Route::post('/receipts/categorize', [BulkOperationController::class, 'bulkCategorize'])->name('receipts.categorize');
        Route::post('/receipts/export/csv', [BulkOperationController::class, 'bulkExportCsv'])->name('receipts.export.csv');
        Route::post('/receipts/export/pdf', [BulkOperationController::class, 'bulkExportPdf'])->name('receipts.export.pdf');
        Route::post('/receipts/stats', [BulkOperationController::class, 'getStats'])->name('receipts.stats');
    });

    // Jobs routes
    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/status', [JobController::class, 'getStatus'])->name('jobs.status');

    // Search routes
    Route::get('/search', [SearchController::class, 'search'])->name('search');

    // Export routes
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('/receipts/csv', [ExportController::class, 'exportCsv'])->name('receipts.csv');
        Route::get('/receipts/pdf', [ExportController::class, 'exportPdf'])->name('receipts.pdf');
        Route::get('/receipt/{id}/pdf', [ExportController::class, 'exportReceiptPdf'])->name('receipt.pdf');
    });

    // PulseDav routes
    Route::prefix('pulsedav')->name('pulsedav.')->group(function () {
        Route::get('/', [PulseDavController::class, 'index'])->name('index');
        Route::post('/sync', [PulseDavController::class, 'sync'])->name('sync');
        Route::post('/process', [PulseDavController::class, 'process'])->name('process');
        Route::get('/files/{id}/status', [PulseDavController::class, 'status'])->name('status');
        Route::delete('/files/{id}', [PulseDavController::class, 'destroy'])->name('destroy');
    });

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notifications/clear', [NotificationController::class, 'clear'])->name('notifications.clear');
});

// Health check endpoint for Docker/Kubernetes
Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('health');

require __DIR__.'/auth.php';
