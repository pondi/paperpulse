<?php

namespace App\Services\Documents;

use App\Models\File;
use App\Models\FileConversion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ConversionService
{
    protected $redisConnection;

    public function __construct()
    {
        $this->redisConnection = Redis::connection('conversion');
    }

    /**
     * Check if file extension requires conversion
     */
    public function requiresConversion(string $extension): bool
    {
        $officeFormats = array_merge(
            config('processing.documents.office_formats.word', []),
            config('processing.documents.office_formats.spreadsheet', []),
            config('processing.documents.office_formats.presentation', []),
            config('processing.documents.office_formats.other', [])
        );

        return in_array(strtolower($extension), $officeFormats);
    }

    /**
     * Queue conversion job to Redis
     */
    public function queueConversion(File $file, string $inputS3Path, string $outputS3Path): FileConversion
    {
        // Create conversion record
        $conversion = FileConversion::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'status' => 'pending',
            'input_extension' => $file->fileExtension,
            'input_s3_path' => $inputS3Path,
            'output_s3_path' => $outputS3Path,
            'retry_count' => 0,
            'max_retries' => config('processing.conversion.max_retries', 3),
        ]);

        // Build job payload
        $payload = [
            'conversionId' => $conversion->id,
            'fileId' => $file->id,
            'fileGuid' => $file->guid,
            'userId' => $file->user_id,
            'inputS3Path' => $inputS3Path,
            'outputS3Path' => $outputS3Path,
            'inputExtension' => $file->fileExtension,
            'retryCount' => 0,
            'maxRetries' => config('processing.conversion.max_retries', 3),
            'createdAt' => now()->toIso8601String(),
            'timeout' => config('processing.conversion.timeout', 120),
        ];

        // Push to Redis pending queue
        $this->redisConnection->lpush(
            config('processing.conversion.redis_queue', 'conversion:pending'),
            json_encode($payload)
        );

        // Set status hash for quick lookups
        $this->redisConnection->hset(
            "conversion:status:{$conversion->id}",
            'status',
            'pending'
        );
        $this->redisConnection->hset(
            "conversion:status:{$conversion->id}",
            'updated_at',
            now()->toIso8601String()
        );
        $this->redisConnection->expire("conversion:status:{$conversion->id}", 7200);

        Log::info('[ConversionService] Queued conversion job', [
            'conversion_id' => $conversion->id,
            'file_id' => $file->id,
            'file_guid' => $file->guid,
            'extension' => $file->fileExtension,
        ]);

        return $conversion;
    }

    /**
     * Poll for conversion completion (blocking with timeout)
     */
    public function waitForCompletion(FileConversion $conversion, int $timeoutSeconds = 120): array
    {
        $startTime = microtime(true);
        $pollingInterval = (float) config('processing.conversion.polling_interval', 1.0);

        if ($pollingInterval <= 0) {
            $pollingInterval = 0.1;
        }

        while ((microtime(true) - $startTime) < $timeoutSeconds) {
            $status = $this->getStatus($conversion->id);

            if (is_null($status)) {
                $conversion->refresh();
                $status = $conversion->status;
            }

            // Redis keeps the latest status; once we see "completed" or "failed" we can refresh to grab metadata.

            if ($status === 'completed') {
                $conversion->refresh();

                return [
                    'success' => true,
                    'output_path' => $conversion->output_s3_path,
                ];
            }

            if ($status === 'failed') {
                $conversion->refresh();

                return [
                    'success' => false,
                    'error' => $conversion->error_message,
                ];
            }

            // Sleep before next poll
            usleep((int) ($pollingInterval * 1000000));
        }

        // Timeout reached
        Log::warning('[ConversionService] Conversion timeout', [
            'conversion_id' => $conversion->id,
            'elapsed_seconds' => microtime(true) - $startTime,
        ]);

        return [
            'success' => false,
            'error' => 'Conversion timeout after '.$timeoutSeconds.' seconds',
        ];
    }

    /**
     * Get conversion status from Redis (faster than DB)
     */
    public function getStatus(int $conversionId): ?string
    {
        $status = $this->redisConnection->hget(
            "conversion:status:{$conversionId}",
            'status'
        );

        return $status ?: null;
    }
}
