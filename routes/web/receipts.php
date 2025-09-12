<?php

use App\Http\Controllers\BulkOperationsController;
use App\Http\Controllers\Receipts\LineItemController;
use App\Http\Controllers\Receipts\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    // Receipt routes
    Route::prefix('receipts')->name('receipts.')->group(function () {
        Route::get('/', [ReceiptController::class, 'index'])->name('index');
        Route::get('/{receipt}', [ReceiptController::class, 'show'])->name('show');
        Route::get('/{receipt}/image', [ReceiptController::class, 'showImage'])->name('showImage');
        Route::get('/{receipt}/pdf', [ReceiptController::class, 'showPdf'])->name('showPdf');
        Route::get('/merchant/{merchant}', [ReceiptController::class, 'byMerchant'])->name('byMerchant');
        Route::delete('/{receipt}', [ReceiptController::class, 'destroy'])->name('destroy');
        Route::patch('/{receipt}', [ReceiptController::class, 'update'])->name('update');
        Route::post('/{receipt}/line-items', [LineItemController::class, 'store'])->name('line-items.store');
        Route::patch('/{receipt}/line-items/{lineItem}', [LineItemController::class, 'update'])->name('line-items.update');
        Route::delete('/{receipt}/line-items/{lineItem}', [LineItemController::class, 'destroy'])->name('line-items.destroy');
        // Use ReceiptController for share/unshare via ShareableController trait
        Route::post('/{receipt}/share', [ReceiptController::class, 'share'])->name('share');
        Route::delete('/{receipt}/share/{user}', [ReceiptController::class, 'unshare'])->name('unshare');
        Route::post('/{receipt}/tags', [ReceiptController::class, 'attachTag'])->name('tags.store');
        Route::delete('/{receipt}/tags/{tag}', [ReceiptController::class, 'detachTag'])->name('tags.destroy');
    });

    // Bulk operations
    Route::prefix('bulk')->name('bulk.')->group(function () {
        Route::post('/receipts/delete', [BulkOperationsController::class, 'bulkDelete'])->name('receipts.delete');
        Route::post('/receipts/categorize', [BulkOperationsController::class, 'bulkCategorize'])->name('receipts.categorize');
        Route::post('/receipts/export/csv', [BulkOperationsController::class, 'bulkExportCsv'])->name('receipts.export.csv');
        Route::post('/receipts/export/pdf', [BulkOperationsController::class, 'bulkExportPdf'])->name('receipts.export.pdf');
        Route::post('/receipts/stats', [BulkOperationsController::class, 'getStats'])->name('receipts.stats');
    });
});
