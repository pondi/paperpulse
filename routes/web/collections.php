<?php

use App\Http\Controllers\CollectionController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PublicCollectionLinkController;
use App\Models\Collection;
use Illuminate\Support\Facades\Route;

// Custom route binding for collections that bypasses the user global scope
// This allows accessing shared collections; authorization is handled by CollectionPolicy
Route::bind('collection', function ($value) {
    return Collection::withoutGlobalScope('user')->findOrFail($value);
});

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::prefix('collections')->name('collections.')->group(function () {
        // Static routes first
        Route::get('/recommendations', [OrganizationController::class, 'index'])->name('organization.index');
        Route::post('/recommendations', [OrganizationController::class, 'start'])->name('organization.start');
        Route::post('/recommendations/decisions', [OrganizationController::class, 'decide'])->name('organization.decide');
        Route::post('/recommendations/{recommendation}/undo', [OrganizationController::class, 'undo'])->name('organization.undo');
        Route::post('/recommendation-runs/{run}/retry', [OrganizationController::class, 'retry'])->name('organization.retry');
        Route::post('/recommendation-runs/{run}/dismiss', [OrganizationController::class, 'dismiss'])->name('organization.dismiss');
        Route::get('/', [CollectionController::class, 'index'])->name('index');
        Route::get('/folders', [CollectionController::class, 'folders'])->name('folders');
        Route::get('/all', [CollectionController::class, 'all'])->name('all');
        Route::get('/shared', [CollectionController::class, 'shared'])->name('shared');
        Route::post('/', [CollectionController::class, 'store'])->name('store');

        // Dynamic routes
        Route::get('/{collection}', [CollectionController::class, 'show'])->name('show');
        Route::patch('/{collection}', [CollectionController::class, 'update'])->name('update');
        Route::delete('/{collection}', [CollectionController::class, 'destroy'])->name('destroy');

        Route::get('/{collection}/tree-preview', [CollectionController::class, 'treePreview'])->name('tree-preview');

        // Archive actions
        Route::post('/{collection}/archive', [CollectionController::class, 'archive'])->name('archive');
        Route::post('/{collection}/unarchive', [CollectionController::class, 'unarchive'])->name('unarchive');

        // File management
        Route::post('/{collection}/files', [CollectionController::class, 'addFiles'])->name('files.add');
        Route::delete('/{collection}/files', [CollectionController::class, 'removeFiles'])->name('files.remove');

        // Sharing
        Route::scopeBindings()->group(function () {
            Route::post('/{collection}/share', [CollectionController::class, 'share'])->name('share');
            Route::delete('/{collection}/share/{user}', [CollectionController::class, 'unshare'])->withoutScopedBindings()->name('unshare');
        });

        // Public links
        Route::post('/{collection}/public-links', [PublicCollectionLinkController::class, 'store'])->name('public-links.store');
        Route::delete('/{collection}/public-links/{publicCollectionLink}', [PublicCollectionLinkController::class, 'destroy'])->name('public-links.destroy');
        Route::get('/{collection}/public-links/{publicCollectionLink}/logs', [PublicCollectionLinkController::class, 'logs'])->name('public-links.logs');
    });
});
