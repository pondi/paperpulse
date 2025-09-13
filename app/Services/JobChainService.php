<?php

namespace App\Services;

use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Documents\ProcessDocument;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Jobs\Receipts\MatchMerchant;
use App\Jobs\Receipts\ProcessReceipt;
use App\Jobs\System\ApplyTags;
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
            $queue = $fileMetadata['fileType'] === 'receipt' ? 'receipts' : 'documents';
            Bus::chain($newJobChain)->onQueue($queue)->dispatch();

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
     * Get file metadata from cache or database
     */
    protected function getOrRebuildFileMetadata(string $jobId, JobHistory $parentJob): ?array
    {
        // Use the persistence service to get metadata
        $metadata = \App\Services\Jobs\JobMetadataPersistence::retrieve($jobId);

        if ($metadata) {
            return $metadata;
        }

        Log::info('File metadata not found, attempting to rebuild', ['job_id' => $jobId]);

        // Try to rebuild from database as fallback for legacy jobs
        return $this->rebuildFileMetadata($jobId, $parentJob);
    }

    /**
     * Rebuild file metadata from database records
     */
    protected function rebuildFileMetadata(string $jobId, JobHistory $parentJob): ?array
    {
        try {
            // Multiple strategies to find the file
            $file = null;

            // Strategy 1: Try to find by exact job_id in metadata (if stored)
            if ($parentJob->metadata && isset($parentJob->metadata['file_id'])) {
                $file = File::find($parentJob->metadata['file_id']);
            }

            // Strategy 2: Search by parent job timestamp and user
            if (! $file) {
                // Get user_id from the first task if available
                $firstTask = $parentJob->tasks()->orderBy('order_in_chain')->first();
                $userId = null;
                if ($firstTask && $firstTask->metadata && isset($firstTask->metadata['user_id'])) {
                    $userId = $firstTask->metadata['user_id'];
                }

                // Find files created around the same time
                $query = File::whereBetween('created_at', [
                    $parentJob->created_at->subSeconds(30),
                    $parentJob->created_at->addMinutes(2),
                ]);

                if ($userId) {
                    $query->where('user_id', $userId);
                }

                $file = $query->orderBy('created_at', 'desc')->first();
            }

            // Strategy 3: Try to find via receipt relationship
            if (! $file) {
                $receipt = Receipt::whereBetween('created_at', [
                    $parentJob->created_at,
                    $parentJob->created_at->addMinutes(5),
                ])->first();

                if ($receipt) {
                    $file = $receipt->file;
                }
            }

            // Strategy 4: Find the most recent file that matches the time window
            if (! $file) {
                $file = File::whereBetween('created_at', [
                    $parentJob->created_at->subMinutes(10),
                    $parentJob->created_at->addMinutes(10),
                ])->orderBy('created_at', 'desc')->first();
            }

            if (! $file) {
                Log::warning('Could not find file for job chain restart', ['job_id' => $jobId]);

                return null;
            }

            // Rebuild the metadata
            // Try to preserve original tag IDs from parent job metadata
            $originalMetadata = $parentJob->metadata['metadata'] ?? [];
            $tagIds = $originalMetadata['tagIds'] ?? [];

            $metadata = [
                'fileId' => $file->id,
                'fileGuid' => $file->guid,
                'fileName' => $file->fileName,
                'filePath' => storage_path('app/uploads/'.$file->guid.'.'.$file->fileExtension),
                'fileExtension' => $file->fileExtension,
                'fileSize' => $file->fileSize,
                'fileType' => $file->file_type,
                'userId' => $file->user_id,
                's3OriginalPath' => $file->s3_original_path,
                'jobName' => $parentJob->name ?? 'Restarted Job',
                'metadata' => array_merge($file->meta ?? [], [
                    'tagIds' => $tagIds,
                    'source' => $originalMetadata['source'] ?? 'upload',
                ]),
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

        // Normalize job name for comparison (handle both with and without spaces)
        $jobName = str_replace(' ', '', $restartPoint['name']);

        switch ($jobName) {
            case 'ProcessFile':
                $jobs[] = (new ProcessFile($jobId))->onQueue($queue);
                // Fall through to add subsequent jobs
                if ($fileType === 'receipt') {
                    $jobs[] = (new ProcessReceipt($jobId))->onQueue($queue);
                    $jobs[] = (new MatchMerchant($jobId))->onQueue($queue);
                } else {
                    $jobs[] = (new ProcessDocument($jobId))->onQueue($queue);
                    $jobs[] = (new AnalyzeDocument($jobId))->onQueue($queue);
                }
                break;

            case 'ProcessReceipt':
                if ($fileType === 'receipt') {
                    $jobs[] = (new ProcessReceipt($jobId))->onQueue($queue);
                    $jobs[] = (new MatchMerchant($jobId))->onQueue($queue);
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

            case 'ApplyTags':
                // Tags will be re-applied at the end of the chain
                break;
        }

        // Add ApplyTags job if there are tags to apply
        $tagIds = $fileMetadata['metadata']['tagIds'] ?? [];
        if (! empty($tagIds) && isset($fileMetadata['fileId'])) {
            $file = \App\Models\File::find($fileMetadata['fileId']);
            if ($file) {
                $jobs[] = (new ApplyTags($jobId, $file, $tagIds))->onQueue($queue);
            }
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
