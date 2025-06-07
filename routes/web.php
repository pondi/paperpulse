<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PulseDavController;
use App\Http\Controllers\ExportController;
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

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

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
});

require __DIR__.'/auth.php';
