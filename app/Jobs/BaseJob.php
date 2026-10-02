<?php

namespace App\Jobs;

use App\Events\FileExtractionCompleted;
use App\Jobs\BankStatements\ProcessCsvImport;
use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Files\ProcessFileGemini;
use App\Jobs\Receipts\ProcessReceipt;
use App\Models\File;
use App\Models\FileOrganizationRequest;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\File\FileStorageService;
use App\Services\Files\FileEntityCleanupService;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\Jobs\JobParentStatusCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Base job providing common queue behavior and job history tracking.
 *
 * Features:
 * - Validated construction with a non-empty chain JobID
 * - UUID per task within the chain
 * - Automatic JobHistory create/update with progress + status
 * - Centralized error handling and parent status aggregation
 *
 * @property string $jobID Parent job chain identifier
 * @property string|null $uuid Unique identifier for this task
 * @property string $jobName Human-readable job name for UI/logs
 */
abstract class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Default timeout for all jobs (5 minutes). Override in subclasses as needed.
     */
    public int $timeout = 300;

    /**
     * Default retry attempts. Override in subclasses as needed.
     */
    public int $tries = 3;

    /**
     * Default backoff in seconds between retries. Override in subclasses.
     *
     * @var int|array
     */
    public $backoff = [30, 60, 120];

    /**
     * The unique identifier for this job chain.
     */
    public string $jobID;

    /**
     * The unique identifier for this specific task.
     */
    public ?string $uuid = null;

    /**
     * The name of this job for display purposes.
     */
    public string $jobName;

    /**
     * The originating API request ID for end-to-end tracing.
     */
    public ?string $requestId = null;

    /**
     * Flag to prevent double-handling of failures.
     */
    protected bool $failureHandled = false;

    /**
     * Create a new job instance.
     *
     * @param  string  $jobID  Non-empty parent job chain ID
     */
    public function __construct(string $jobID)
    {
        if (empty($jobID)) {
            throw new InvalidArgumentException('JobID cannot be empty');
        }
        $this->uuid = (string) Str::uuid();
        $this->jobID = $jobID;
        $this->jobName = class_basename($this);
    }

    /**
     * Get the job ID.
     */
    public function getJobID(): string
    {
        return $this->jobID;
    }

    /**
     * Get the job UUID.
     */
    public function getUUID(): ?string
    {
        return $this->uuid;
    }

    /**
     * Get the job name.
     */
    public function getJobName(): string
    {
        return $this->jobName;
    }

    /**
     * Execute the job. Handles JobHistory lifecycle and error tracking.
     */
    final public function handle(): void
    {
        $metadata = $this->getMetadata();
        $ownerId = $metadata['userId'] ?? ($this->pulseDavFile->user_id ?? null);
        if (isset($metadata['fileId'])) {
            (new File)->getConnection()->transaction(function () use ($metadata, $ownerId): void {
                $file = File::withoutGlobalScope('user')->where('user_id', $ownerId)->lockForUpdate()->find($metadata['fileId']);
                if (! $file || ($file->meta['processing_generation'] ?? null) !== ($metadata['processingGeneration'] ?? null)) {
                    JobHistory::query()->whereIn('uuid', [$this->uuid, $this->jobID])->update(['status' => 'cancelled', 'finished_at' => now()]);
                    $this->delete();

                    return;
                }
                $replace = ($metadata['metadata']['reprocessing'] ?? false) && $this->commitsExtraction()
                    && ! JobHistory::query()->where('uuid', $this->uuid)->where('status', 'completed')->exists();
                if ($replace) {
                    app(FileEntityCleanupService::class)->softDeleteAndUnindexEntities($file);
                }
                $this->execute();
                if ($replace && ! app(FileEntityCleanupService::class)->hasEntities($file)) {
                    throw new RuntimeException('Replacement extraction produced no entities.');
                }
                if ($this->commitsExtraction() && JobHistory::query()->where('uuid', $this->uuid)->where('status', 'completed')->exists()
                    && $file->primaryEntity()->where('user_id', $file->user_id)->exists()) {
                    $generation = $metadata['processingGeneration'];
                    FileOrganizationRequest::query()->firstOrCreate([
                        'file_id' => $file->id, 'generation' => $generation,
                    ], ['user_id' => $file->user_id]);
                    FileExtractionCompleted::dispatch($file->user_id, $file->id, $generation);
                }
            });

            return;
        }
        if ($ownerId !== null) {
            (new User)->getConnection()->transaction(function () use ($ownerId): void {
                if (! User::query()->whereKey($ownerId)->sharedLock()->first()) {
                    $this->delete();

                    return;
                }
                $this->execute();
            });

            return;
        }
        $this->execute();
    }

    protected function commitsExtraction(): bool
    {
        return $this instanceof ProcessFileGemini
            || $this instanceof ProcessReceipt
            || $this instanceof AnalyzeDocument
            || $this instanceof ProcessCsvImport;
    }

    protected function execute(): void
    {
        if (JobHistory::query()->whereIn('uuid', [$this->uuid, $this->jobID])->where('status', 'cancelled')->exists()) {
            $this->delete();

            return;
        }
        if (empty($this->jobID)) {
            Log::error('JobID is empty in handle', [
                'class' => static::class,
                'uuid' => $this->uuid,
            ]);
            throw new RuntimeException('JobID cannot be empty');
        }

        // Ensure we have a UUID
        if (! $this->uuid) {
            $this->uuid = (string) Str::uuid();
        }

        if (JobHistory::query()->where('uuid', $this->uuid)->where('status', 'completed')->exists()) {
            $this->restoreCompletedDelivery();

            return;
        }

        // Add request ID to log context for end-to-end tracing
        if ($this->requestId) {
            Log::withContext(['request_id' => $this->requestId]);
        }

        // Create or update job history record
        $this->createOrUpdateJobHistory();

        try {
            $this->handleJob();
            $this->markAsCompleted();
        } catch (Throwable $e) {
            JobHistory::query()->where('uuid', $this->uuid)->update([
                'status' => 'retrying',
                'exception' => $this->exceptionMessageForStorage($e),
                'finished_at' => null,
            ]);
            $this->updateParentJobStatus();
            throw $e;
        }
    }

    /**
     * Execute the job's logic.
     *
     * Implemented by subclasses.
     */
    abstract protected function handleJob(): void;

    protected function restoreCompletedDelivery(): void {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('processing-step:'.$this->uuid))->shared()
            ->releaseAfter(30)->expireAfter($this->timeout + 60)];
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'job:'.$this->jobID,
            'task:'.$this->uuid,
            'type:'.class_basename($this),
        ];
    }

    /**
     * Store metadata for this job.
     */
    protected function storeMetadata(array $metadata): void
    {
        if (empty($this->jobID)) {
            Log::error('JobID is empty in storeMetadata', [
                'class' => static::class,
                'uuid' => $this->uuid,
            ]);
            throw new RuntimeException('JobID cannot be empty');
        }

        JobMetadataPersistence::store($this->jobID, $metadata);
    }

    /**
     * Get metadata for this job.
     */
    protected function getMetadata(): ?array
    {
        if (empty($this->jobID)) {
            Log::error('JobID is empty in getMetadata', [
                'class' => static::class,
                'uuid' => $this->uuid,
            ]);
            throw new RuntimeException('JobID cannot be empty');
        }

        return JobMetadataPersistence::retrieve($this->jobID);
    }

    /**
     * Update the job's progress.
     *
     * @param  int  $progress  0-100
     */
    protected function updateProgress(int $progress): void
    {
        if (! $this->uuid) {
            $this->uuid = (string) Str::uuid();
        }

        JobHistory::where('uuid', $this->uuid)
            ->update(['progress' => $progress]);
    }

    /**
     * Create or update job history record.
     */
    protected function createOrUpdateJobHistory(): void
    {
        $metadata = $this->getMetadata();
        JobHistory::query()->firstOrCreate(['uuid' => $this->jobID], [
            'parent_uuid' => null,
            'name' => $metadata['jobName'] ?? 'Processing Job',
            'queue' => $this->queue ?? 'default',
            'status' => 'pending',
            'metadata' => $metadata,
            'order_in_chain' => 0,
            'request_id' => $this->requestId,
        ]);

        // Create the individual task record
        $data = [
            'uuid' => $this->uuid,
            'parent_uuid' => $this->jobID,
            'name' => $this->jobName,
            'queue' => property_exists($this, 'queue') && $this->queue ? $this->queue : 'default',
            'status' => 'processing',
            'started_at' => now(),
            'attempt' => $this->attempts() ?? 1,
            'order_in_chain' => $this->getOrderInChain(),
            'finished_at' => null,
            'exception' => null,
        ];

        JobHistory::updateOrCreate(
            ['uuid' => $this->uuid],
            $data
        );
    }

    /**
     * Get the order in the job chain.
     */
    protected function getOrderInChain(): int
    {
        foreach ($this->getMetadata()['plannedSteps'] ?? [] as $step) {
            if ($step['uuid'] === $this->uuid) {
                return $step['order'];
            }
        }

        return JobOrder::getOrder($this->jobName);
    }

    /**
     * Get the chain name based on the job type.
     */
    protected function getChainName(): string
    {
        // Try to get the friendly job name from metadata
        $metadata = $this->getMetadata();
        if ($metadata && isset($metadata['jobName'])) {
            return $metadata['jobName'];
        }

        // Fallback based on job order/type
        return match ($this->getOrderInChain()) {
            1 => 'File Processing Job',
            2 => str_contains($this->jobName, 'Receipt') ? 'Receipt Processing Job' : 'Document Processing Job',
            default => 'Processing Job',
        };
    }

    /**
     * Mark the job as completed and update parent status.
     */
    protected function markAsCompleted(): void
    {
        JobHistory::where('uuid', $this->uuid)
            ->update([
                'status' => 'completed',
                'finished_at' => now(),
                'progress' => 100,
            ]);

        // Check if this is the last job in the chain and update parent status
        $this->updateParentJobStatus();
    }

    /**
     * Update the parent job status based on children completion.
     */
    protected function updateParentJobStatus(): void
    {
        JobParentStatusCalculator::update($this->jobID);
    }

    /**
     * Mark the job as failed and update parent status.
     */
    public function failed(Throwable $exception): void
    {
        $metadata = $this->getMetadata();
        if (isset($metadata['fileId'])) {
            $file = File::withoutGlobalScope('user')->where('user_id', $metadata['userId'])->find($metadata['fileId']);
            if (! $file || ($file->meta['processing_generation'] ?? null) !== ($metadata['processingGeneration'] ?? null)) {
                return;
            }
        }
        $ownerId = $this->getMetadata()['userId'] ?? null;
        if (($ownerId !== null && ! User::query()->whereKey($ownerId)->exists())
            || JobHistory::query()->where('uuid', $this->jobID)->where('status', 'cancelled')->exists()) {
            return;
        }
        // Prevent double-handling of failures
        if ($this->failureHandled) {
            return;
        }
        $this->failureHandled = true;

        if (! $this->uuid) {
            $this->uuid = (string) Str::uuid();
        }

        $this->createOrUpdateJobHistory();
        JobHistory::query()->where('parent_uuid', $this->jobID)->whereIn('status', ['pending', 'queued'])
            ->where('uuid', '!=', $this->uuid)->update(['status' => 'cancelled', 'finished_at' => now()]);

        $exceptionMessage = $this->exceptionMessageForStorage($exception);

        $this->persistFailureToJobHistory($exceptionMessage, $exception);

        try {
            $metadata = $this->getMetadata();
            $fileId = $metadata['fileId'] ?? null;

            if ($fileId) {
                $file = File::find($fileId);
                if ($file && in_array($file->status, ['pending', 'processing'], true)) {
                    $fileMeta = $file->meta ?? [];
                    $fileMeta['last_processing_error'] = [
                        'message' => $exceptionMessage,
                        'job' => static::class,
                        'job_id' => $this->jobID,
                        'failed_at' => now()->toISOString(),
                    ];

                    $file->status = 'failed';
                    $file->meta = $fileMeta;
                    $file->save();
                }
            }
        } catch (Throwable $metadataError) {
            Log::warning('Failed to update file status after job failure', [
                'class' => static::class,
                'job_id' => $this->jobID,
                'uuid' => $this->uuid,
                'error' => $metadataError->getMessage(),
            ]);
        }

        app(FileStorageService::class)->cleanupJob($this->jobID);

        Log::error('Job failed', [
            'class' => static::class,
            'job_id' => $this->jobID,
            'uuid' => $this->uuid,
            'error' => $exceptionMessage,
            'exception_class' => $exception::class,
        ]);
    }

    protected function persistFailureToJobHistory(string $exceptionMessage, Throwable $exception): void
    {
        $updatePayload = [
            'status' => 'failed',
            'finished_at' => now(),
            'exception' => $exceptionMessage,
        ];

        $attempts = [
            $updatePayload,
            array_replace($updatePayload, ['exception' => Str::limit($exceptionMessage, 500, '…')]),
            array_replace($updatePayload, ['exception' => Str::limit($exceptionMessage, 200, '…')]),
            array_replace($updatePayload, ['exception' => 'Job failed; see logs for details.']),
        ];

        $lastError = null;

        foreach ($attempts as $payload) {
            try {
                JobHistory::where('uuid', $this->uuid)->update($payload);

                try {
                    $this->updateParentJobStatus();
                } catch (Throwable $parentUpdateError) {
                    Log::warning('Failed to update parent job status after child failure', [
                        'class' => static::class,
                        'job_id' => $this->jobID,
                        'uuid' => $this->uuid,
                        'error' => $parentUpdateError->getMessage(),
                    ]);
                }

                return;
            } catch (Throwable $e) {
                $lastError = $e;
            }
        }

        Log::warning('Failed to persist job failure to job_history', [
            'class' => static::class,
            'job_id' => $this->jobID,
            'uuid' => $this->uuid,
            'error' => $lastError?->getMessage(),
            'original_exception_class' => $exception::class,
            'original_exception_message' => $exceptionMessage,
        ]);
    }

    protected function exceptionMessageForStorage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        $message = str_replace(["\r\n", "\r", "\n"], ' ', $message);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $message);
            if ($converted !== false) {
                $message = $converted;
            }
        }

        $message = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $message) ?? $message;
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;
        $message = trim($message);

        if ($message === '') {
            $message = class_basename($exception);
        }

        return Str::limit($message, 2000, '…');
    }
}
