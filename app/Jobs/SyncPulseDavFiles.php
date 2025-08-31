<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ScannerFilesImported;
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

                    // Auto-process scanner uploads if user preference is enabled
                    if ($user->preference('auto_process_scanner_uploads', false)) {
                        $unprocessedFiles = $user->pulseDavFiles()
                            ->where('status', 'pending')
                            ->get();

                        foreach ($unprocessedFiles as $file) {
                            ProcessPulseDavFile::dispatch($file);
                        }

                        Log::info('Auto-processing queued for scanner files', [
                            'user_id' => $user->id,
                            'files_queued' => $unprocessedFiles->count(),
                        ]);
                    }

                    // Notify user about new scanner files
                    if ($user->preference('notify_scanner_imports')) {
                        $user->notify(new ScannerFilesImported($synced));
                    }
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
