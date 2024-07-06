<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/file/upload', function () {
    return Inertia::render('File/Upload');
})->middleware(['auth', 'verified'])->name('file.upload');

Route::post('/file/store', [FileController::class, 'store'])->middleware(['auth', 'verified'])->name('file.store');

Route::get('/receipt/vendors', function () {
    return Inertia::render('Receipt/Vendors');
})->middleware(['auth', 'verified'])->name('receipt.vendors');

require __DIR__.'/auth.php';
