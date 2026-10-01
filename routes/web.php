<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationRequestController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes
Route::get('/', function () {
    $props = [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ];

    if (app()->environment('local')) {
        $props['laravelVersion'] = Application::VERSION;
        $props['phpVersion'] = PHP_VERSION;
    }

    return Inertia::render('Welcome', $props);
});

Route::post('/invitation-request', [InvitationRequestController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('invitation.request');

// Authenticated & Inertia routes
Route::middleware(['auth', 'verified', 'web'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Scanner
    Route::get('/scanner', function () {
        return Inertia::render('Scanner/Index');
    })->name('scanner');
});

Route::get('/ready', App\Http\Controllers\HealthController::class)->name('health');

// Include domain-specific routes
require __DIR__.'/auth.php';
require __DIR__.'/web/documents.php';
require __DIR__.'/web/receipts.php';
require __DIR__.'/web/invoices.php';
require __DIR__.'/web/contracts.php';
require __DIR__.'/web/vouchers.php';
require __DIR__.'/web/files.php';
require __DIR__.'/web/profile.php';
require __DIR__.'/web/admin.php';
require __DIR__.'/web/integrations.php';
require __DIR__.'/web/collections.php';
require __DIR__.'/web/duplicates.php';
require __DIR__.'/web/bank-statements.php';
require __DIR__.'/web/shared.php';
