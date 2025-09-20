<?php

namespace App\Services\PulseDav;

use App\Notifications\ScannerFilesImported;

class ScannerImportNotifier
{
    public static function maybeNotify(\App\Models\User $user, int $synced): void
    {
        if ($synced <= 0 || ! $user->preferences) {
            return;
        }
        if ($user->preferences->notify_scanner_import) {
            try {
                $user->notify(new ScannerFilesImported($synced));
            } catch (\Throwable $e) {
                // Swallow errors to avoid impacting sync
            }
        }
    }
}
