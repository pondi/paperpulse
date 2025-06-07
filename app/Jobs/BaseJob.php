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
    protected string $jobID;

    /**
     * The unique identifier for this specific task.
     */
    protected ?string $uuid = null;

    /**
     * The name of this job for display purposes.
     */
    protected string $jobName;

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

        try {
            $this->handleJob();
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

        return Cache::get("job.{$this->jobID}.fileMetaData");
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

        Log::error('Job failed', [
            'class' => static::class,
            'job_id' => $this->jobID,
            'uuid' => $this->uuid,
            'error' => $exception->getMessage(),
        ]);
    }
}
