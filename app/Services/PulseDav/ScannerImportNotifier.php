<?php

namespace App\Services\PulseDav;

use App\Models\PulseDavImportBatch;
use App\Notifications\ScannerFilesImported;

class ScannerImportNotifier
{
    public static function notifyBatch(int $batchId): void
    {
        (new PulseDavImportBatch)->getConnection()->transaction(function () use ($batchId): void {
            $batch = PulseDavImportBatch::query()->lockForUpdate()->findOrFail($batchId);
            $counts = $batch->files()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
            $completed = (int) ($counts['completed'] ?? 0);
            $failed = (int) ($counts['failed'] ?? 0);
            $batch->update(['completed_count' => $completed, 'failed_count' => $failed]);
            if ($batch->notified_at !== null || $batch->file_count === 0 || $completed + $failed !== $batch->file_count) {
                return;
            }
            $batch->update(['notified_at' => now()]);
            if ($batch->user->preference('notify_scanner_import', false)) {
                $batch->user->notify((new ScannerFilesImported($batch->file_count, $completed, $failed))->afterCommit());
            }
        });
    }
}
