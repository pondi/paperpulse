<?php

namespace App\Services\PulseDav;

use App\Jobs\PulseDav\ProcessPulseDavFile;
use App\Models\PulseDavFile;
use App\Models\PulseDavImportBatch;
use App\Services\PulseDav\Import\S3PathResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ImportService
{
    public static function importFile(PulseDavFile $file, PulseDavImportBatch $batch, string $fileType, array $tagIds, ?string $note = null): void
    {
        if ($file->user_id !== $batch->user_id || ! S3PathResolver::pathExists($file->s3_path, $file->user_id)) {
            throw ValidationException::withMessages(['s3_path' => 'Invalid scanner file for this batch.']);
        }

        Log::info('[PulseDavImport] Importing file', [
            'file_id' => $file->id,
            'filename' => $file->filename,
            's3_path' => $file->s3_path,
            'file_type' => $fileType,
            'tag_ids' => $tagIds,
            'note' => $note,
        ]);

        $inheritedTags = $file->inherited_tags->pluck('id')->toArray();
        $allTagIds = array_unique(array_merge($tagIds, $inheritedTags));

        $file->update([
            'file_type' => $fileType,
            'import_batch_id' => $batch->id,
            'status' => 'processing',
        ]);

        ProcessPulseDavFile::dispatch($file, $allTagIds, $note)->onQueue('default');
    }
}
