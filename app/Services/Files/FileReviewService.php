<?php

namespace App\Services\Files;

use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Services\Jobs\JobHistoryCreator;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileReviewService
{
    public function resolve(File $file, string $type): void
    {
        $file->getConnection()->transaction(function () use ($file, $type): void {
            $locked = File::query()->lockForUpdate()->findOrFail($file->id);
            if ($locked->status !== 'needs_review' || ($locked->meta['review']['reason'] ?? null) !== 'uncertain_classification') {
                throw ValidationException::withMessages(['file_type' => 'This file requires review of its processing limits or totals before retrying.']);
            }
            $jobId = (string) Str::uuid();
            $meta = $locked->meta ?? [];
            $meta['processing_generation'] = $jobId;
            $meta['review']['corrected_type'] = $type;
            $meta['review']['corrected_at'] = now()->toIso8601String();
            $locked->update(['meta' => $meta, 'status' => 'pending', 'file_type' => $type]);
            $metadata = ['fileId' => $locked->id, 'fileGuid' => $locked->guid, 'fileExtension' => $locked->fileExtension,
                'fileType' => $type, 'userId' => $locked->user_id, 's3OriginalPath' => $locked->s3_original_path,
                's3ArchivePath' => $locked->s3_archive_path, 'processingGeneration' => $jobId, 'targetedExtraction' => true,
                'jobName' => 'Resolve file review'];
            JobHistoryCreator::createParentJob($jobId, 'Resolve file review', $type, $metadata, $locked->id, $locked->fileName);
            JobMetadataPersistence::store($jobId, $metadata);
            ProcessFileGemini::dispatch($jobId)->onQueue('files')->afterCommit();
        });
    }
}
