<?php

namespace App\Jobs;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class BaseProcessingJob extends BaseJob
{
    protected string $jobType;

    /**
     * Execute the job's logic with common processing pattern.
     */
    protected function handleJob(): void
    {
        $debugEnabled = config('app.debug');
        $startTime = microtime(true);

        if ($debugEnabled) {
            Log::debug("[{$this->jobName}] Job starting", [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'job_name' => $this->jobName,
                'timestamp' => now()->toISOString(),
            ]);
        }

        try {
            // Step 1: Get and validate metadata
            $metadata = $this->getMetadata();
            if (!$metadata) {
                throw new Exception('No metadata found for job');
            }

            $this->validateMetadata($metadata);

            if ($debugEnabled) {
                Log::debug("[{$this->jobName}] Metadata loaded", [
                    'job_id' => $this->jobID,
                    'metadata' => $metadata,
                ]);
            }

            $this->updateProgress(10);

            // Step 2: Process the file (implemented by subclasses)
            $result = $this->processFile($metadata);

            // Step 3: Update progress and handle completion
            $this->updateProgress(90);

            $this->handleCompletion($result, $metadata);

            $this->updateProgress(100);

            $elapsed = microtime(true) - $startTime;

            Log::info("[{$this->jobName}] Job completed successfully", [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'elapsed_time' => round($elapsed, 2),
                'result' => $result,
            ]);

        } catch (Exception $e) {
            $elapsed = microtime(true) - $startTime;

            Log::error("[{$this->jobName}] Job failed", [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'error' => $e->getMessage(),
                'elapsed_time' => round($elapsed, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->handleError($e);

            throw $e;
        }
    }

    /**
     * Validate the metadata structure.
     */
    protected function validateMetadata(array $metadata): void
    {
        $requiredFields = ['fileGuid', 'fileId'];
        
        foreach ($requiredFields as $field) {
            if (!isset($metadata[$field])) {
                throw new Exception("Missing required metadata field: {$field}");
            }
        }
    }

    /**
     * Process the file - implemented by subclasses.
     */
    abstract protected function processFile(array $metadata): array;

    /**
     * Handle successful completion - can be overridden by subclasses.
     */
    protected function handleCompletion(array $result, array $metadata): void
    {
        // Default implementation does nothing
        // Subclasses can override to send notifications, etc.
    }

    /**
     * Handle job errors - can be overridden by subclasses.
     */
    protected function handleError(Exception $e): void
    {
        // Default implementation just sets error status
        Cache::put("job:{$this->jobID}:error", $e->getMessage(), 3600);
    }

    /**
     * Log processing step with consistent format.
     */
    protected function logProcessingStep(string $step, array $context = []): void
    {
        Log::info("[{$this->jobName}] {$step}", array_merge([
            'job_id' => $this->jobID,
            'task_id' => $this->uuid,
        ], $context));
    }
}