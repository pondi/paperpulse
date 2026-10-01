<?php

namespace App\Services\Jobs;

use App\Models\File;
use App\Models\JobHistory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class JobMetadataPersistence
{
    public static function store(string $jobId, array $metadata): void
    {
        $metadata = self::normalize($metadata);
        JobHistory::query()->updateOrCreate(['uuid' => $jobId], [
            'metadata' => $metadata,
        ] + (JobHistory::query()->where('uuid', $jobId)->exists() ? [] : [
            'name' => $metadata['jobName'] ?? 'Processing Job',
            'queue' => ($metadata['fileType'] ?? null) === 'receipt' ? 'receipts' : 'documents',
            'status' => 'pending',
            'order_in_chain' => 0,
            'file_id' => $metadata['fileId'] ?? null,
        ]));
        self::cache($jobId, $metadata);
    }

    public static function normalize(array $metadata): array
    {
        if (isset($metadata['fileId'])) {
            $file = File::withoutGlobalScope('user')->find($metadata['fileId']);
            if (! $file) {
                throw new RuntimeException('The processing source file no longer exists.');
            }
            if (isset($metadata['userId']) && (int) $metadata['userId'] !== (int) $file->user_id) {
                throw new AuthorizationException('The processing source owner does not match.');
            }
            $meta = $file->meta ?? [];
            if (empty($meta['processing_generation'])) {
                $meta['processing_generation'] = (string) Str::uuid();
                $file->meta = $meta;
                $file->save();
            }
            $metadata['userId'] = (int) $file->user_id;
            $metadata['processingGeneration'] ??= $meta['processing_generation'];
            $metadata['fileGuid'] ??= $file->guid;
            $metadata['fileType'] ??= $file->file_type;
            $metadata['s3OriginalPath'] ??= $file->s3_original_path;
        }
        $metadata['schemaVersion'] = 1;
        $metadata['processingProvider'] ??= config('ai.file_processing_provider', 'textract+openai');
        $metadata['pipeline'] ??= strtolower($metadata['fileExtension'] ?? '') === 'csv' ? 'csv' : $metadata['processingProvider'];

        return $metadata;
    }

    public static function retrieve(string $jobId): ?array
    {
        $metadata = JobHistory::query()->where('uuid', $jobId)->value('metadata');
        if ($metadata) {
            self::cache($jobId, $metadata);

            return $metadata;
        }
        try {
            $legacy = Cache::get("job.{$jobId}.fileMetaData");
            if (is_array($legacy) && $legacy !== []) {
                self::store($jobId, $legacy);

                return JobHistory::query()->where('uuid', $jobId)->value('metadata');
            }
        } catch (Throwable $exception) {
            Log::warning('Legacy job metadata cache unavailable', ['job_id' => $jobId, 'error' => $exception->getMessage()]);
        }

        return null;
    }

    protected static function cache(string $jobId, array $metadata): void
    {
        try {
            Cache::put("job.{$jobId}.fileMetaData", $metadata, now()->addHours(4));
        } catch (Throwable $exception) {
            Log::warning('Job metadata cache unavailable; durable metadata retained', ['job_id' => $jobId, 'error' => $exception->getMessage()]);
        }
    }
}
