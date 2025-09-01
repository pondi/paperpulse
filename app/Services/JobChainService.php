<?php

namespace App\Services;

use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Documents\ProcessDocument;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Jobs\Receipts\MatchMerchant;
use App\Jobs\Receipts\ProcessReceipt;
use App\Models\File;
use App\Models\JobHistory;
use App\Models\Receipt;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class JobChainService
{
    /**
     * Restart a failed job chain from the point of failure
     */
    public function restartJobChain(string $jobId): array
    {
        try {
            Log::info('Starting job chain restart', ['job_id' => $jobId]);

            // Get the parent job and all its tasks
            $parentJob = JobHistory::where('uuid', $jobId)->first();
            if (! $parentJob) {
                throw new \Exception("Job chain not found: {$jobId}");
            }

            $tasks = $parentJob->tasks()->orderBy('order_in_chain')->get();

            // Find the first failed task or the last incomplete task
            $restartPoint = $this->findRestartPoint($tasks);

            if (! $restartPoint) {
                throw new \Exception('No restart point found - all tasks may already be completed');
            }

            // Get file metadata from cache or rebuild it
            $fileMetadata = $this->getOrRebuildFileMetadata($jobId, $parentJob);
            if (! $fileMetadata) {
                throw new \Exception('Could not retrieve file metadata for job chain restart');
            }

            // Build the job chain from the restart point
            $newJobChain = $this->buildJobChainFromRestartPoint($restartPoint, $fileMetadata, $jobId);

            if (empty($newJobChain)) {
                throw new \Exception('No jobs to restart');
            }

            // Dispatch the new job chain
            Bus::chain($newJobChain)->dispatch();

            Log::info('Job chain restarted successfully', [
                'job_id' => $jobId,
                'restart_point' => $restartPoint['name'],
                'jobs_count' => count($newJobChain),
            ]);

            return [
                'success' => true,
                'message' => "Job chain restarted from {$restartPoint['name']}",
                'restart_point' => $restartPoint['name'],
                'jobs_count' => count($newJobChain),
            ];

        } catch (\Exception $e) {
            Log::error('Job chain restart failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find the point from which to restart the job chain
     */
    protected function findRestartPoint($tasks): ?array
    {
        foreach ($tasks as $task) {
            if ($task->status === 'failed') {
                return [
                    'name' => $task->name,
                    'order' => $task->order_in_chain,
                    'uuid' => $task->uuid,
                ];
            }
        }

        // If no failed task, find the first incomplete task
        foreach ($tasks as $task) {
            if ($task->status !== 'completed') {
                return [
                    'name' => $task->name,
                    'order' => $task->order_in_chain,
                    'uuid' => $task->uuid,
                ];
            }
        }

        return null;
    }

    /**
     * Get file metadata from cache or rebuild it from database
     */
    protected function getOrRebuildFileMetadata(string $jobId, JobHistory $parentJob): ?array
    {
        // Try to get from cache first
        $metadata = Cache::get("job.{$jobId}.fileMetaData");
        if ($metadata) {
            return $metadata;
        }

        Log::info('File metadata not in cache, rebuilding', ['job_id' => $jobId]);

        // Try to rebuild from database
        return $this->rebuildFileMetadata($jobId, $parentJob);
    }

    /**
     * Rebuild file metadata from database records
     */
    protected function rebuildFileMetadata(string $jobId, JobHistory $parentJob): ?array
    {
        try {
            // Find the file record associated with this job
            $file = File::whereHas('receipts', function ($query) use ($parentJob) {
                // Try to find via receipt
                $receiptData = Cache::get("job.{$parentJob->uuid}.receiptMetaData");
                if ($receiptData && isset($receiptData['receiptId'])) {
                    $query->where('id', $receiptData['receiptId']);
                }
            })->orWhereHas('documents', function ($query) use ($parentJob) {
                // Try to find via document
                $query->whereRaw('created_at >= ? AND created_at <= ?', [
                    $parentJob->created_at->subMinutes(5),
                    $parentJob->created_at->addMinutes(5),
                ]);
            })->first();

            if (! $file) {
                // Try a broader search by timestamp
                $file = File::whereBetween('created_at', [
                    $parentJob->created_at->subMinutes(10),
                    $parentJob->created_at->addMinutes(10),
                ])->first();
            }

            if (! $file) {
                Log::warning('Could not find file for job chain restart', ['job_id' => $jobId]);

                return null;
            }

            // Rebuild the metadata
            $metadata = [
                'fileId' => $file->id,
                'fileGuid' => $file->guid,
                'filePath' => storage_path('app/uploads/'.$file->guid.'.'.$file->fileExtension),
                'fileExtension' => $file->fileExtension,
                'fileType' => $file->file_type,
                'userId' => $file->user_id,
                's3OriginalPath' => $file->s3_original_path,
                'jobName' => $parentJob->name ?? 'Restarted Job',
                'metadata' => [],
            ];

            // Cache the rebuilt metadata
            Cache::put("job.{$jobId}.fileMetaData", $metadata, now()->addHours(4));

            Log::info('File metadata rebuilt successfully', [
                'job_id' => $jobId,
                'file_id' => $file->id,
                'file_guid' => $file->guid,
            ]);

            return $metadata;

        } catch (\Exception $e) {
            Log::error('Failed to rebuild file metadata', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build a new job chain starting from the restart point
     */
    protected function buildJobChainFromRestartPoint(array $restartPoint, array $fileMetadata, string $jobId): array
    {
        $jobs = [];
        $fileType = $fileMetadata['fileType'];
        $queue = $fileType === 'receipt' ? 'receipts' : 'documents';

        switch ($restartPoint['name']) {
            case 'ProcessFile':
                $jobs[] = (new ProcessFile($jobId))->onQueue($queue);
                // Fall through to add subsequent jobs

            case 'ProcessReceipt':
                if ($fileType === 'receipt') {
                    $jobs[] = (new ProcessReceipt($jobId))->onQueue($queue);
                    // MatchMerchant will be dispatched by ProcessReceipt
                }
                break;

            case 'ProcessDocument':
                if ($fileType === 'document') {
                    $jobs[] = (new ProcessDocument($jobId))->onQueue($queue);
                    $jobs[] = (new AnalyzeDocument($jobId))->onQueue($queue);
                }
                break;

            case 'MatchMerchant':
                if ($fileType === 'receipt') {
                    // Try to get receipt data for merchant matching
                    $receiptData = Cache::get("job.{$jobId}.receiptMetaData");
                    if ($receiptData) {
                        $jobs[] = (new MatchMerchant(
                            $jobId,
                            $receiptData['receiptId'],
                            $receiptData['merchantName'] ?? '',
                            $receiptData['merchantAddress'] ?? '',
                            $receiptData['merchantVatID'] ?? ''
                        ))->onQueue($queue);
                    } else {
                        Log::warning('Cannot restart MatchMerchant without receipt data', ['job_id' => $jobId]);
                    }
                }
                break;

            case 'AnalyzeDocument':
                if ($fileType === 'document') {
                    $jobs[] = (new AnalyzeDocument($jobId))->onQueue($queue);
                }
                break;
        }

        // Always add cleanup job at the end
        if (! empty($jobs)) {
            $jobs[] = (new DeleteWorkingFiles($jobId))->onQueue($queue);
        }

        return $jobs;
    }

    /**
     * Get job chain status information
     */
    public function getJobChainStatus(string $jobId): array
    {
        $parentJob = JobHistory::where('uuid', $jobId)->first();
        if (! $parentJob) {
            return ['found' => false];
        }

        $tasks = $parentJob->tasks()->orderBy('order_in_chain')->get();

        $status = [
            'found' => true,
            'job_id' => $jobId,
            'name' => $parentJob->name,
            'status' => $parentJob->status,
            'created_at' => $parentJob->created_at->toISOString(),
            'tasks' => [],
            'can_restart' => false,
        ];

        foreach ($tasks as $task) {
            $status['tasks'][] = [
                'uuid' => $task->uuid,
                'name' => $task->name,
                'status' => $task->status,
                'order' => $task->order_in_chain,
                'progress' => $task->progress,
                'started_at' => $task->started_at?->toISOString(),
                'finished_at' => $task->finished_at?->toISOString(),
                'exception' => $task->exception,
            ];

            // Check if chain can be restarted
            if ($task->status === 'failed') {
                $status['can_restart'] = true;
            }
        }

        return $status;
    }

    /**
     * Mark failed jobs for restart
     */
    public function markJobsForRestart(array $jobIds): array
    {
        $results = [];

        foreach ($jobIds as $jobId) {
            $results[$jobId] = $this->restartJobChain($jobId);
        }

        return $results;
    }
}
