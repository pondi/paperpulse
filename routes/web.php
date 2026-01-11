<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationRequestController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

// Health check endpoint for Docker/Kubernetes
Route::get('/up', function () {
    $status = 'ok';
    $checks = [];

    // Check database connection
    try {
        DB::connection()->getPdo();
        $checks['database'] = true;
    } catch (Exception) {
        $status = 'error';
        $checks['database'] = false;
    }

    // Check Redis connection
    try {
        Cache::store('redis')->get('health-check');
        $checks['redis'] = true;
    } catch (Exception) {
        $status = 'error';
        $checks['redis'] = false;
    }

    // Check if migrations are up to date
    try {
        $pendingMigrations = collect(DB::select('SELECT migration FROM migrations'))
            ->pluck('migration')
            ->diff(collect(File::files(database_path('migrations')))
                ->map(fn ($file) => str_replace('.php', '', $file->getFilename()))
            )
            ->isEmpty();
        $checks['migrations'] = $pendingMigrations;
    } catch (Exception) {
        $checks['migrations'] = false;
    }

    return response()->json([
        'status' => $status,
        'timestamp' => now()->toIso8601String(),
        'checks' => $checks,
    ], $status === 'ok' ? 200 : 503);
})->name('health');

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
