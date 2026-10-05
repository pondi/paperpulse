<?php

use App\Http\Controllers\LibraryController;
use App\Http\Controllers\SavedSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function (): void {
    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/saved-views', [SavedSearchController::class, 'index'])->name('saved-searches.index');
    Route::post('/saved-views', [SavedSearchController::class, 'store'])->name('saved-searches.store');
    Route::get('/saved-views/{savedSearch}', [SavedSearchController::class, 'show'])->name('saved-searches.show');
    Route::patch('/saved-views/{savedSearch}', [SavedSearchController::class, 'update'])->name('saved-searches.update');
    Route::delete('/saved-views/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('saved-searches.destroy');
});
