<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PulseDavService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPulseDavFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(PulseDavService $pulseDavService)
    {
        Log::info('Starting PulseDav file sync for all users');

        $users = User::whereHas('pulseDavFiles')
            ->orWhereHas('receipts')
            ->get();

        $totalSynced = 0;

        foreach ($users as $user) {
            try {
                $synced = $pulseDavService->syncS3Files($user);
                $totalSynced += $synced;

                if ($synced > 0) {
                    Log::info('Synced PulseDav files for user', [
                        'user_id' => $user->id,
                        'synced_count' => $synced,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync PulseDav files for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed PulseDav file sync', [
            'total_users' => $users->count(),
            'total_synced' => $totalSynced,
        ]);
    }
}