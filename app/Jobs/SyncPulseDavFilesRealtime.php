<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PulseDavService;
use App\Notifications\ScannerFilesImported;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPulseDavFilesRealtime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job for users with real-time sync enabled.
     */
    public function handle(PulseDavService $pulseDavService)
    {
        // Only sync for users who have enabled real-time sync
        $users = User::whereHas('preference', function ($query) {
            $query->where('pulsedav_realtime_sync', true);
        })->get();

        if ($users->isEmpty()) {
            return;
        }

        Log::info('Starting real-time PulseDav file sync', [
            'user_count' => $users->count(),
        ]);

        foreach ($users as $user) {
            try {
                $synced = $pulseDavService->syncS3Files($user);

                if ($synced > 0) {
                    Log::info('Real-time sync found new files', [
                        'user_id' => $user->id,
                        'synced_count' => $synced,
                    ]);
                    
                    // Notify user immediately
                    if ($user->preference('notify_scanner_imports')) {
                        $user->notify(new ScannerFilesImported($synced));
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed real-time sync for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}