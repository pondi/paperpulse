<?php

namespace App\Services\PulseDav\Import;

use App\Models\PulseDavFile;
use App\Models\PulseDavImportBatch;
use App\Models\User;
use App\Services\PulseDav\ImportService;
use App\Services\PulseDav\Support\PathHelper;
use Exception;
use Illuminate\Support\Facades\Log;

class ImportProcessor
{
    public static function processItem(array $selection, User $user, PulseDavImportBatch $batch, array $options): bool
    {
        if ($batch->user_id !== $user->id || empty(ImportValidator::validateSelections([$selection], $user)['valid'])) {
            return false;
        }

        $file = S3PathResolver::resolveToRecord($selection['s3_path'], $user);

        if (! $file) {
            Log::info('[ImportProcessor] Creating missing file record', [
                's3_path' => $selection['s3_path'],
                'user_id' => $user->id,
            ]);

            try {
                $file = FileRecordCreator::createFromS3Path($selection['s3_path'], $user);
            } catch (Exception $e) {
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

        return ImportService::importFile($file, $batch, $options['file_type'] ?? 'receipt', $options['tag_ids'] ?? [], $options['notes'] ?? null);
    }

    private static function processFolderImport(PulseDavFile $folder, PulseDavImportBatch $batch, array $options): bool
    {
        $files = PathHelper::withinFolder(PulseDavFile::where('user_id', $folder->user_id), $folder->folder_path)
            ->filesOnly()
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        $imported = 0;
        foreach ($files as $file) {
            $imported += (int) ImportService::importFile(
                $file,
                $batch,
                $options['file_type'] ?? 'receipt',
                $options['tag_ids'] ?? [],
                $options['notes'] ?? null
            );
        }

        return $imported > 0;
    }
}
