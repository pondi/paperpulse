<?php

namespace App\Services;

use App\Http\Requests\StoreBatchRequest;
use App\Jobs\Files\ProcessBatchItem;
use App\Models\BatchJob;
use App\Models\File;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BatchProcessingService
{
    /**
     * Process multiple documents in batch
     */
    public function processBatch(
        array $items,
        User $user,
        string $type = 'document',
        array $options = []
    ): BatchJob {
        Validator::make(compact('items', 'type', 'options'), (new StoreBatchRequest)->rules())->validate();
        $ownedFileIds = File::withoutGlobalScope('user')->where('user_id', $user->id)
            ->where('file_type', $type)->whereIn('id', array_column($items, 'file_id'))->pluck('id');
        $errors = [];
        foreach ($items as $index => $item) {
            if (! $ownedFileIds->contains($item['file_id']) || ($item['type'] ?? $type) !== $type) {
                $errors["items.{$index}.file_id"] = 'Select an owned file matching the batch type.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        try {
            DB::beginTransaction();

            // Create batch job record
            $batchJob = BatchJob::create([
                'user_id' => $user->id,
                'type' => $type,
                'total_items' => count($items),
                'processed_items' => 0,
                'failed_items' => 0,
                'status' => 'queued',
                'options' => $options,
                'estimated_cost' => 0.0,
                'started_at' => now(),
            ]);

            // Simplified batch configuration
            $batchConfig = [
                'batch_size' => $this->calculateOptimalBatchSize(count($items), null, $options),
                'parallel_jobs' => $this->calculateOptimalParallelism(count($items), $options),
            ];

            Log::info('[BatchProcessingService] Starting batch processing', [
                'batch_id' => $batchJob->id,
                'user_id' => $user->id,
                'type' => $type,
                'total_items' => count($items),
                'estimated_cost' => $batchJob->estimated_cost,
            ]);

            // Create batch items and dispatch jobs
            $this->createAndDispatchBatchItems($batchJob, $items, $batchConfig, $options);

            DB::commit();

            return $batchJob;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('[BatchProcessingService] Batch creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'items_count' => count($items),
            ]);

            throw $e;
        }
    }

    /**
     * Create batch items and dispatch processing jobs
     */
    protected function createAndDispatchBatchItems(
        BatchJob $batchJob,
        array $items,
        array $batchConfig,
        array $options
    ): void {
        $chunks = array_chunk($items, $batchConfig['batch_size']);
        $delay = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            // Create batch items
            $batchItems = [];
            foreach ($chunk as $itemIndex => $item) {
                $batchItems[] = [
                    'batch_job_id' => $batchJob->id,
                    'item_index' => $chunkIndex * $batchConfig['batch_size'] + $itemIndex,
                    'source' => (string) $item['file_id'],
                    'type' => $item['type'] ?? $batchJob->type,
                    'options' => json_encode(array_merge($options, $item['options'] ?? [])),
                    'status' => 'queued',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert batch items
            DB::table('batch_items')->insert($batchItems);

            $itemIds = $batchJob->items()->whereBetween('item_index', [
                $chunkIndex * $batchConfig['batch_size'],
                $chunkIndex * $batchConfig['batch_size'] + count($chunk) - 1,
            ])->orderBy('item_index')->pluck('id')->all();

            ProcessBatchItem::dispatch($batchJob->id, $itemIds)
                ->delay(now()->addSeconds($delay))
                ->onQueue($this->getQueueForBatch($batchJob, $options));

            // Stagger job dispatch to avoid overwhelming the system
            $delay += $this->calculateStaggerDelay($batchConfig, $options);
        }

        // Update batch job status
        $batchJob->update(['status' => 'processing']);
    }

    /**
     * Update batch progress
     */
    public function updateBatchProgress(int $batchJobId): void
    {
        (new BatchJob)->getConnection()->transaction(function () use ($batchJobId): void {
            $batchJob = BatchJob::withoutGlobalScope('user')->lockForUpdate()->findOrFail($batchJobId);
            $totals = $batchJob->items()->selectRaw("COUNT(CASE WHEN status IN ('completed', 'failed') THEN 1 END) AS processed_count")
                ->selectRaw("COUNT(CASE WHEN status = 'failed' THEN 1 END) AS failed_count")
                ->selectRaw('COALESCE(SUM(cost), 0) AS total_cost')->first();
            $attributes = [
                'processed_items' => $totals->processed_count,
                'failed_items' => $totals->failed_count,
                'actual_cost' => $totals->total_cost,
            ];
            if ($totals->processed_count >= $batchJob->total_items) {
                $attributes['status'] = $totals->failed_count > 0 ? 'completed_with_errors' : 'completed';
                $attributes['completed_at'] = $batchJob->completed_at ?? now();
            }
            $batchJob->update($attributes);
        });
    }

    /**
     * Get batch processing status
     */
    public function getBatchStatus(int $batchJobId, ?User $user = null): array
    {
        $query = BatchJob::where('id', $batchJobId);

        if ($user) {
            $query->where('user_id', $user->id);
        }

        $batchJob = $query->firstOrFail();

        $progress = $batchJob->total_items > 0
            ? ($batchJob->processed_items / $batchJob->total_items) * 100
            : 0;

        return [
            'id' => $batchJob->id,
            'status' => $batchJob->status,
            'progress' => round($progress, 1),
            'total_items' => $batchJob->total_items,
            'processed_items' => $batchJob->processed_items,
            'failed_items' => $batchJob->failed_items,
            'estimated_cost' => $batchJob->estimated_cost,
            'actual_cost' => $batchJob->actual_cost,
            'started_at' => $batchJob->started_at,
            'completed_at' => $batchJob->completed_at,
            'duration' => $batchJob->completed_at
                ? $batchJob->completed_at->diffInSeconds($batchJob->started_at)
                : $batchJob->started_at->diffInSeconds(now()),
        ];
    }

    /**
     * Cancel a batch job
     */
    public function cancelBatch(int $batchJobId, ?User $user = null): bool
    {
        try {
            $query = BatchJob::where('id', $batchJobId);

            if ($user) {
                $query->where('user_id', $user->id);
            }

            $batchJob = $query->firstOrFail();

            if (in_array($batchJob->status, ['completed', 'cancelled'])) {
                return false; // Cannot cancel completed or already cancelled batches
            }

            // Cancel queued jobs
            $this->cancelQueuedJobs($batchJob);

            $batchJob->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

            Log::info('[BatchProcessingService] Batch cancelled', [
                'batch_id' => $batchJobId,
                'processed_items' => $batchJob->processed_items,
                'total_items' => $batchJob->total_items,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('[BatchProcessingService] Failed to cancel batch', [
                'batch_id' => $batchJobId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // Helper methods
    protected function determineBatchBudget(int $itemCount, array $options): string
    {
        if ($itemCount > 1000) {
            return 'economy';
        } elseif ($itemCount > 100) {
            return 'standard';
        } else {
            return $options['budget'] ?? 'standard';
        }
    }

    protected function calculateOptimalBatchSize(
        int $itemCount,
        $model,
        array $options
    ): int {
        $baseBatchSize = $options['batch_size'] ?? 10;

        // Adjust based on item count
        if ($itemCount > 1000) {
            $baseBatchSize = min(50, $baseBatchSize * 2);
        }

        return max(1, $baseBatchSize);
    }

    protected function calculateOptimalParallelism(int $itemCount, array $options): int
    {
        $maxParallel = $options['max_parallel'] ?? 5;

        if ($itemCount > 1000) {
            return min($maxParallel, 10);
        } elseif ($itemCount > 100) {
            return min($maxParallel, 5);
        } else {
            return min($maxParallel, 3);
        }
    }

    protected function getQueueForBatch(BatchJob $batchJob, array $options): string
    {
        if ($batchJob->total_items > 1000) {
            return 'batch-large';
        } elseif ($batchJob->total_items > 100) {
            return 'batch-medium';
        } else {
            return 'batch-small';
        }
    }

    protected function calculateStaggerDelay(array $batchConfig, array $options): int
    {
        // Calculate delay between job dispatches to prevent rate limiting
        $baseDelay = $options['stagger_delay'] ?? 1; // seconds

        // Conservative default without batch API
        return $baseDelay * 2;
    }

    protected function cancelQueuedJobs(BatchJob $batchJob): void
    {
        // This would cancel queued jobs - implementation depends on queue driver
        // For Redis/Database queue, you might delete jobs from the queue tables
        Log::info('[BatchProcessingService] Cancelling queued jobs for batch', [
            'batch_id' => $batchJob->id,
        ]);
    }
}
