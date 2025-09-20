<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
});

// Health check endpoint for Docker/Kubernetes
Route::get('/up', function () {
    $status = 'ok';
    $checks = [];

    // Check database connection
    try {
        DB::connection()->getPdo();
        $checks['database'] = true;
    } catch (\Exception $e) {
        $status = 'error';
        $checks['database'] = false;
    }

    // Check Redis connection
    try {
        Cache::store('redis')->get('health-check');
        $checks['redis'] = true;
    } catch (\Exception $e) {
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
    } catch (\Exception $e) {
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
require __DIR__.'/web/profile.php';
require __DIR__.'/web/admin.php';
require __DIR__.'/web/integrations.php';
