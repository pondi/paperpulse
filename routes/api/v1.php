<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankStatementController;
use App\Http\Controllers\Api\V1\BulkUploadController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\FileContentController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\VoucherController;
use App\Http\Controllers\Api\V1\WarrantyController;
use Illuminate\Support\Facades\Route;

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,15');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Protected API routes
Route::middleware(['auth:sanctum', 'api.rate_limit:200,1'])->group(function () {
    // File upload & listing (single file upload only)
    Route::get('files', [FileController::class, 'index'])->name('api.files.index');
    Route::get('files/{file}', [FileController::class, 'show'])->name('api.files.show');
    Route::get('files/{file}/content', [FileContentController::class, 'show'])->name('api.files.content');
    Route::post('files', [FileController::class, 'store'])->name('api.files.store');
    Route::patch('files/{file}', [FileController::class, 'update'])->name('api.files.update');
    Route::delete('files/{file}', [FileController::class, 'destroy'])->name('api.files.destroy');

    // Search
    Route::get('search', [SearchController::class, 'index'])->name('api.search');

    // Tags
    Route::get('tags', [TagController::class, 'index'])->name('api.tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('api.tags.store');
    Route::patch('tags/{tag}', [TagController::class, 'update'])->name('api.tags.update');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('api.tags.destroy');

    // Collections
    Route::get('collections', [CollectionController::class, 'index'])->name('api.collections.index');
    Route::post('collections', [CollectionController::class, 'store'])->name('api.collections.store');
    Route::patch('collections/{collection}', [CollectionController::class, 'update'])->name('api.collections.update');
    Route::delete('collections/{collection}', [CollectionController::class, 'destroy'])->name('api.collections.destroy');

    // Jobs
    Route::get('jobs/{jobId}', [JobController::class, 'show'])->name('api.jobs.show');

    // Receipts
    Route::get('receipts', [ReceiptController::class, 'index'])->name('api.receipts.index');
    Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('api.receipts.show');

    // Invoices
    Route::get('invoices', [InvoiceController::class, 'index'])->name('api.invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('api.invoices.show');

    // Contracts
    Route::get('contracts', [ContractController::class, 'index'])->name('api.contracts.index');
    Route::get('contracts/{contract}', [ContractController::class, 'show'])->name('api.contracts.show');

    // Bank Statements
    Route::get('bank-statements', [BankStatementController::class, 'index'])->name('api.bank-statements.index');
    Route::get('bank-statements/{bankStatement}', [BankStatementController::class, 'show'])->name('api.bank-statements.show');

    // Vouchers
    Route::get('vouchers', [VoucherController::class, 'index'])->name('api.vouchers.index');
    Route::get('vouchers/{voucher}', [VoucherController::class, 'show'])->name('api.vouchers.show');

    // Warranties
    Route::get('warranties', [WarrantyController::class, 'index'])->name('api.warranties.index');
    Route::get('warranties/{warranty}', [WarrantyController::class, 'show'])->name('api.warranties.show');

    // Bulk Upload (Uplink)
    Route::prefix('bulk')->group(function () {
        Route::get('sessions', [BulkUploadController::class, 'index'])
            ->name('api.bulk.sessions.index');
        Route::post('sessions', [BulkUploadController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('api.bulk.sessions.store');
        Route::get('sessions/{uuid}', [BulkUploadController::class, 'show'])
            ->name('api.bulk.sessions.show');
        Route::post('sessions/{uuid}/presign', [BulkUploadController::class, 'presign'])
            ->middleware('throttle:60,1')
            ->name('api.bulk.sessions.presign');
        Route::post('sessions/{uuid}/files/{fileUuid}/presign', [BulkUploadController::class, 'presignFile'])
            ->middleware('throttle:60,1')
            ->name('api.bulk.files.presign');
        Route::post('sessions/{uuid}/files/{fileUuid}/confirm', [BulkUploadController::class, 'confirmFile'])
            ->middleware('throttle:600,1')
            ->name('api.bulk.files.confirm');
        Route::post('sessions/{uuid}/cancel', [BulkUploadController::class, 'cancel'])
            ->name('api.bulk.sessions.cancel');
    });
});
