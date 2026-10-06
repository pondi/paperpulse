<?php

use App\Jobs\Maintenance\CleanupRetainedFiles;
use App\Jobs\Notifications\SendWeeklySummary;
use App\Jobs\PulseDav\SyncPulseDavFiles;
use App\Jobs\PulseDav\SyncPulseDavFilesRealtime;
use App\Services\File\FileStorageService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule PulseDav sync every 30 minutes
Schedule::job(new SyncPulseDavFiles)->everyThirtyMinutes()
    ->name('sync-pulsedav-files')
    ->withoutOverlapping();

// Schedule real-time PulseDav sync every 5 minutes for users who enabled it
Schedule::job(new SyncPulseDavFilesRealtime)->everyFiveMinutes()
    ->name('sync-pulsedav-files-realtime')
    ->withoutOverlapping();

// Schedule user file retention cleanup daily at 3am
Schedule::job(new CleanupRetainedFiles)->dailyAt('03:00')
    ->name('cleanup-retained-files')
    ->withoutOverlapping();

// Check each user's local reporting day and time.
Schedule::job(new SendWeeklySummary)->hourly()
    ->name('send-weekly-summaries')
    ->withoutOverlapping();

// Schedule permanent deletion of soft-deleted records after 30 days, daily at 4am
Schedule::command('cleanup:soft-deleted --days=30')->dailyAt('04:00')
    ->name('cleanup-soft-deleted-records')
    ->withoutOverlapping();

// Clean up expired bulk upload sessions and orphaned S3 files daily at 5am
Schedule::command('bulk:cleanup-expired --hours=48')->dailyAt('05:00')
    ->name('cleanup-expired-bulk-sessions')
    ->withoutOverlapping();

// Deactivate expired public collection links daily at 6am
Schedule::command('public-links:cleanup')->dailyAt('06:00')
    ->name('cleanup-expired-public-links')
    ->withoutOverlapping();

Schedule::command('files:recover-processing')->everyMinute()
    ->name('recover-file-processing-requests')->withoutOverlapping();

Schedule::command('notify:expiring-vouchers --days=30')->dailyAt('08:00')->timezone('UTC')
    ->name('notify-expiring-vouchers')->withoutOverlapping();

Schedule::command('notify:expiring-warranties --days=30')->dailyAt('08:00')->timezone('UTC')
    ->name('notify-expiring-warranties')->withoutOverlapping();

Schedule::command('bulk:reconcile')->everyFiveMinutes()->name('reconcile-bulk-uploads')->withoutOverlapping();

Schedule::command('conversions:retry-failed --limit=100')->everyFiveMinutes()->name('recover-office-conversions')->withoutOverlapping();

Artisan::command('files:cleanup-working', function (FileStorageService $storage): void {
    $this->info('Removed '.$storage->cleanupOldWorkingFiles().' working files.');
})->purpose('Clean abandoned and terminal job working directories');
Schedule::command('files:cleanup-working')->dailyAt('02:00')->name('cleanup-working-files')->withoutOverlapping();

Schedule::command('pulsedav:reconcile-imports')->everyFiveMinutes()->name('reconcile-scanner-imports')->withoutOverlapping();

Schedule::command('organization:recover-placements')->everyFiveMinutes()->name('recover-file-organization')->withoutOverlapping();

Schedule::command('organization:repair')->everyFiveMinutes()->name('repair-archive-organization')->withoutOverlapping();

Schedule::command('organization:plan')->everyFiveMinutes()->name('plan-organization')->withoutOverlapping();

Schedule::command('organization:backfill --recover')->everyFiveMinutes()->name('recover-organization-backfills')->withoutOverlapping();

Schedule::command('exports:cleanup')->hourly()->name('cleanup-expired-exports')->withoutOverlapping();

Schedule::command('files:recover-automatic')->everyFiveMinutes()->name('recover-automatic-file-processing')->withoutOverlapping();
