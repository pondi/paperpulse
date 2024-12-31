<?php

namespace App\Jobs;

use App\Models\JobMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobUuid;
    protected $jobMonitor;

    public function __construct()
    {
        $this->jobUuid = (string) Str::uuid();
        $this->createJobMonitor();
    }

    protected function createJobMonitor()
    {
        $this->jobMonitor = JobMonitor::create([
            'job_uuid' => $this->jobUuid,
            'batch_id' => $this->batch()?->id,
            'name' => static::class,
            'status' => 'queued',
            'queue' => $this->queue ?? 'default',
            'payload' => $this->payload(),
            'queued_at' => now(),
        ]);
    }

    public function handle()
    {
        $this->jobMonitor->update([
            'status' => 'processing',
            'started_at' => now(),
            'attempt' => $this->attempts(),
        ]);

        try {
            $result = $this->execute();
            
            $this->jobMonitor->update([
                'status' => 'completed',
                'progress' => 100,
                'finished_at' => now(),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->jobMonitor->update([
                'status' => 'failed',
                'exception' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    protected function payload(): array
    {
        return [
            'id' => $this->jobUuid,
            'displayName' => class_basename(static::class),
            'data' => $this->getPayloadData(),
        ];
    }

    abstract protected function execute();
    abstract protected function getPayloadData(): array;

    public function updateProgress(int $progress)
    {
        $this->jobMonitor->update(['progress' => $progress]);
    }
} 