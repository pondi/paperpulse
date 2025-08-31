<?php

namespace App\Jobs;

use App\Models\JobHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
     * Create a new job instance.
     */
    public function __construct(string $jobID)
    {
        if (empty($jobID)) {
            throw new \InvalidArgumentException('JobID cannot be empty');
        }
        $this->jobID = $jobID;
        $this->jobName = class_basename($this);
    }

    /**
     * Get the job's payload.
     */
    public function payload(): array
    {
        if (! $this->uuid) {
            $this->uuid = (string) Str::uuid();
        }

        if (empty($this->jobID)) {
            Log::error('JobID is empty in payload', [
                'class' => static::class,
                'uuid' => $this->uuid,
            ]);
            throw new \RuntimeException('JobID cannot be empty');
        }

        return [
            'uuid' => $this->uuid,
            'data' => [
                'commandName' => $this->jobName,
                'jobID' => $this->jobID,
                'command' => serialize($this),
            ],
        ];
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
     * Execute the job.
     */
    final public function handle(): void
    {
        if (empty($this->jobID)) {
            Log::error('JobID is empty in handle', [
                'class' => static::class,
                'uuid' => $this->uuid,
            ]);
            throw new \RuntimeException('JobID cannot be empty');
        }

        // Ensure we have a UUID
        if (! $this->uuid) {
            $this->uuid = (string) Str::uuid();
        }

        // Create or update job history record
        $this->createOrUpdateJobHistory();

        try {
            $this->handleJob();
            $this->markAsCompleted();
        } catch (\Throwable $e) {
            $this->failed($e);
            throw $e;
        }
    }

    /**
     * Execute the job's logic.
     */
    abstract protected function handleJob(): void;

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
            throw new \RuntimeException('JobID cannot be empty');
        }

        Cache::put(
            "job.{$this->jobID}.fileMetaData",
            $metadata,
            now()->addMinutes(60)
        );
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
            throw new \RuntimeException('JobID cannot be empty');
        }

        $metadata = Cache::get("job.{$this->jobID}.fileMetaData");

        if (! $metadata) {
            Log::warning('No metadata found in cache', [
                'job_id' => $this->jobID,
                'class' => static::class,
                'uuid' => $this->uuid,
                'cache_key' => "job.{$this->jobID}.fileMetaData",
            ]);
        }

        return $metadata;
    }

    /**
     * Update the job's progress.
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
        // Check if this is the first job in the chain (order = 1)
        $isFirstInChain = $this->getOrderInChain() === 1;
        
        if ($isFirstInChain) {
            // This is the parent job - create it with jobID as uuid and no parent
            // Get metadata directly since we're in the processing context
            $metadata = $this->getMetadata();
            $chainName = 'Processing Job'; // Default fallback
            
            if ($metadata && isset($metadata['jobName'])) {
                $chainName = $metadata['jobName'];
            } elseif ($metadata && isset($metadata['fileName'])) {
                $chainName = 'Processing: ' . $metadata['fileName'];
            } else {
                // Fallback based on job type
                $chainName = match ($this->getOrderInChain()) {
                    1 => 'File Processing Job',
                    2 => str_contains($this->jobName, 'Receipt') ? 'Receipt Processing Job' : 'Document Processing Job',
                    default => 'Processing Job',
                };
            }

            $data = [
                'uuid' => $this->jobID,
                'parent_uuid' => null,
                'name' => $chainName,
                'queue' => property_exists($this, 'queue') && $this->queue ? $this->queue : 'default',
                'status' => 'processing',
                'started_at' => now(),
                'attempt' => 1,
                'order_in_chain' => 0, // Parent job has order 0
            ];

            JobHistory::updateOrCreate(
                ['uuid' => $this->jobID],
                $data
            );
        }

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
        return match ($this->jobName) {
            'Process File', 'ProcessFile' => 1,
            'Process Receipt', 'ProcessReceipt' => 2,
            'Process Document', 'ProcessDocument' => 2,
            'Match Merchant', 'MatchMerchant' => 3,
            'Analyze Document', 'AnalyzeDocument' => 3,
            'Apply Tags', 'ApplyTags' => 4,
            'Delete Working Files', 'DeleteWorkingFiles' => 5,
            default => 0,
        };
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
     * Mark the job as completed.
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
        $parentJob = JobHistory::where('uuid', $this->jobID)->first();
        if (!$parentJob) {
            return;
        }

        $childJobs = JobHistory::where('parent_uuid', $this->jobID)->get();
        
        if ($childJobs->isEmpty()) {
            return;
        }

        // Calculate overall status
        $allCompleted = $childJobs->every('status', 'completed');
        $anyFailed = $childJobs->contains('status', 'failed');
        $anyProcessing = $childJobs->contains('status', 'processing');

        $status = 'pending';
        if ($anyFailed) {
            $status = 'failed';
        } elseif ($allCompleted) {
            $status = 'completed';
        } elseif ($anyProcessing) {
            $status = 'processing';
        }

        // Calculate overall progress
        $totalProgress = $childJobs->sum('progress');
        $avgProgress = $childJobs->count() > 0 ? round($totalProgress / $childJobs->count()) : 0;

        // Update parent job
        $updateData = [
            'status' => $status,
            'progress' => $avgProgress,
        ];

        if ($status === 'completed') {
            $updateData['finished_at'] = now();
        }

        JobHistory::where('uuid', $this->jobID)->update($updateData);
    }

    /**
     * Mark the job as failed.
     */
    public function failed(\Throwable $exception): void
    {
        if (! $this->uuid) {
            $this->uuid = (string) Str::uuid();
        }

        JobHistory::where('uuid', $this->uuid)
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'exception' => $exception->getMessage(),
            ]);

        // Update parent job status
        $this->updateParentJobStatus();

        Log::error('Job failed', [
            'class' => static::class,
            'job_id' => $this->jobID,
            'uuid' => $this->uuid,
            'error' => $exception->getMessage(),
        ]);
    }
}
