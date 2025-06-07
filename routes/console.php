<?php

use App\Jobs\CleanupPulseDavFiles;
use App\Jobs\SyncPulseDavFiles;
use App\Jobs\SyncPulseDavFilesRealtime;
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
Schedule::job(new CleanupPulseDavFiles(30))->dailyAt('02:00')
    ->name('cleanup-pulsedav-files')
    ->withoutOverlapping();
