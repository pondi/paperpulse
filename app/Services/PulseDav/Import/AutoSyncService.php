<?php

namespace App\Services\PulseDav\Import;

use App\Models\User;
use App\Services\PulseDavService;
use Illuminate\Support\Facades\Log;

class AutoSyncService
{
    public static function syncBeforeImport(User $user): int
    {
        Log::info('[AutoSyncService] Starting pre-import sync', [
            'user_id' => $user->id
        ]);
        
        try {
            $service = app(PulseDavService::class);
            $synced = $service->syncS3FilesWithFolders($user);
            
            Log::info('[AutoSyncService] Sync completed', [
                'user_id' => $user->id,
                'synced_count' => $synced
            ]);
            
            return $synced;
        } catch (\Exception $e) {
            Log::error('[AutoSyncService] Sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }
}