<?php

namespace App\Services\PulseDav\Import;

use App\Models\PulseDavFile;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class SmartSyncService
{
    /**
     * Only sync specific S3 paths if they don't exist in database
     */
    public static function syncSelectionsIfNeeded(User $user, array $selections): int
    {
        $synced = 0;

        foreach ($selections as $selection) {
            if (empty($selection['s3_path'])) {
                continue;
            }

            // Check if record already exists
            $exists = PulseDavFile::where('user_id', $user->id)
                ->where('s3_path', $selection['s3_path'])
                ->exists();

            if (! $exists && S3PathResolver::pathExists($selection['s3_path'])) {
                try {
                    FileRecordCreator::createFromS3Path($selection['s3_path'], $user);
                    $synced++;
                    Log::info('[SmartSyncService] Created missing file record', [
                        's3_path' => $selection['s3_path'],
                    ]);
                } catch (Exception $e) {
                    Log::error('[SmartSyncService] Failed to create file record', [
                        's3_path' => $selection['s3_path'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $synced;
    }
}
