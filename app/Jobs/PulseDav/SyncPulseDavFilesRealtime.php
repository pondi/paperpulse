<?php

namespace App\Jobs\PulseDav;

use App\Models\User;
use App\Services\PulseDav\ImportService;
use App\Services\PulseDavService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPulseDavFilesRealtime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public function __construct()
    {
        $this->onConnection('database');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('scanner-sync'))->shared()->dontRelease()->expireAfter($this->timeout + 60)];
    }

    /**
     * Execute the job for users with real-time sync enabled.
     */
    public function handle(PulseDavService $pulseDavService): void
    {
        User::whereHas('preferences', fn ($query) => $query->where('pulsedav_realtime_sync', true))
            ->chunkById(100, function ($users) use ($pulseDavService): void {
                foreach ($users as $user) {
                    try {
                        $synced = $pulseDavService->syncS3Files($user);

                        if ($synced > 0) {
                            Log::info('Real-time sync found new files', [
                                'user_id' => $user->id,
                                'synced_count' => $synced,
                            ]);

                            // Auto-process scanner uploads if user preference is enabled
                            if ($user->preference('auto_process_scanner_uploads', false)) {
                                $user->pulseDavFiles()->where('status', 'pending')->chunkById(100, function ($files): void {
                                    foreach ($files as $file) {
                                        ImportService::importFile($file, null, $file->file_type);
                                    }
                                });
                            }
                        }
                    } catch (Exception $e) {
                        Log::error('Failed real-time sync for user', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
