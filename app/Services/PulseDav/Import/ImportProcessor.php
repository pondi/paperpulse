<?php

namespace App\Services\PulseDav\Import;

use App\Models\PulseDavFile;
use App\Models\PulseDavImportBatch;
use App\Models\User;
use App\Services\PulseDav\ImportService;
use Illuminate\Support\Facades\Log;

class ImportProcessor
{
    public static function processItem(array $selection, User $user, PulseDavImportBatch $batch, array $options): bool
    {
        $file = S3PathResolver::resolveToRecord($selection['s3_path'], $user);

        if (! $file) {
            Log::info('[ImportProcessor] Creating missing file record', [
                's3_path' => $selection['s3_path'],
                'user_id' => $user->id,
            ]);

            try {
                $file = FileRecordCreator::createFromS3Path($selection['s3_path'], $user);
            } catch (\Exception $e) {
                Log::error('[ImportProcessor] Failed to create file record', [
                    's3_path' => $selection['s3_path'],
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        if ($file->is_folder) {
            return self::processFolderImport($file, $batch, $options);
        }

        // Check if file can be processed or needs re-processing
        if ($file->status === 'completed') {
            Log::debug('[ImportProcessor] File already completed', [
                'file_id' => $file->id,
                'status' => $file->status,
            ]);

            return false;
        }

        // If already processing but from a different batch, allow re-import
        // This handles stuck files or re-imports
        if ($file->status === 'processing' && $file->import_batch_id !== $batch->id) {
            Log::info('[ImportProcessor] Re-importing file that was stuck in processing', [
                'file_id' => $file->id,
                'old_batch_id' => $file->import_batch_id,
                'new_batch_id' => $batch->id,
            ]);
        }

        ImportService::importFile($file, $batch, $options['file_type'] ?? 'receipt', $options['tag_ids'] ?? []);

        return true;
    }

    private static function processFolderImport(PulseDavFile $folder, PulseDavImportBatch $batch, array $options): bool
    {
        $files = PulseDavFile::where('user_id', $folder->user_id)
            ->where('folder_path', 'like', $folder->folder_path.'%')
            ->filesOnly()
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        $imported = 0;
        foreach ($files as $file) {
            ImportService::importFile($file, $batch, $options['file_type'] ?? 'receipt', $options['tag_ids'] ?? []);
            $imported++;
        }

        return $imported > 0;
    }
}
