<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    // Profile routes
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    // Preferences routes
    Route::prefix('preferences')->name('preferences.')->group(function () {
        Route::get('/', [PreferencesController::class, 'index'])->name('index');
        Route::patch('/', [PreferencesController::class, 'update'])->name('update');
        Route::post('/reset', [PreferencesController::class, 'reset'])->name('reset');
    });

    // Categories
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::patch('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
        Route::post('/order', [CategoryController::class, 'updateOrder'])->name('order');
        Route::post('/defaults', [CategoryController::class, 'createDefaults'])->name('defaults');
        Route::post('/{category}/share', [CategoryController::class, 'share'])->name('share');
        Route::delete('/{category}/share/{user}', [CategoryController::class, 'unshare'])->name('unshare');
    });

    // Tag routes
    Route::prefix('tags')->name('tags.')->group(function () {
        Route::get('/', [TagController::class, 'index'])->name('index');
        Route::get('/all', [TagController::class, 'all'])->name('all');
        Route::post('/', [TagController::class, 'store'])->name('store');
        Route::patch('/{tag}', [TagController::class, 'update'])->name('update');
        Route::delete('/{tag}', [TagController::class, 'destroy'])->name('destroy');
        Route::post('/{tag}/merge', [TagController::class, 'merge'])->name('merge');
    });
});
