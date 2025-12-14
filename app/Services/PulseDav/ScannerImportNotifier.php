<?php

namespace App\Services\PulseDav;

use App\Models\User;
use App\Notifications\ScannerFilesImported;
use Throwable;

class ScannerImportNotifier
{
    public static function maybeNotify(User $user, int $synced): void
    {
        if ($synced <= 0 || ! $user->preferences) {
            return;
        }
        if ($user->preferences->notify_scanner_import) {
            try {
                $user->notify(new ScannerFilesImported($synced));
            } catch (Throwable $e) {
                // Swallow errors to avoid impacting sync
            }
        }
    }
}
