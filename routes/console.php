<?php

use App\Jobs\Maintenance\CleanupRetainedFiles;
use App\Jobs\Maintenance\DeletePulseDavFiles;
use App\Jobs\Notifications\SendWeeklySummary;
use App\Jobs\PulseDav\SyncPulseDavFiles;
use App\Jobs\PulseDav\SyncPulseDavFilesRealtime;
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

// Schedule PulseDav cleanup daily at 2am
Schedule::job(new DeletePulseDavFiles(30))->dailyAt('02:00')
    ->name('cleanup-pulsedav-files')
    ->withoutOverlapping();

// Schedule user file retention cleanup daily at 3am
Schedule::job(new CleanupRetainedFiles)->dailyAt('03:00')
    ->name('cleanup-retained-files')
    ->withoutOverlapping();

// Schedule weekly summary emails daily at 9am (will check if it's the right day for each user)
Schedule::job(new SendWeeklySummary)->dailyAt('09:00')
    ->name('send-weekly-summaries')
    ->withoutOverlapping();
