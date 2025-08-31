<?php

namespace App\Services;

use App\Jobs\ProcessBatchItem;
use App\Models\BatchJob;
use App\Models\User;
use App\Services\AI\ModelConfigurationService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class BatchProcessingService
{
    protected ModelConfigurationService $modelService;

    public function __construct(ModelConfigurationService $modelService)
    {
        $this->modelService = $modelService;
    }

    /**
     * Process multiple documents in batch
     */
    public function processBatch(
        array $items,
        User $user,
        string $type = 'document',
        array $options = []
    ): BatchJob {
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
                'estimated_cost' => $this->estimateBatchCost($items, $type, $options),
                'started_at' => now(),
            ]);

            // Optimize model selection for batch processing
            $batchConfig = $this->optimizeBatchConfiguration($items, $type, $options);

            Log::info('[BatchProcessingService] Starting batch processing', [
                'batch_id' => $batchJob->id,
                'user_id' => $user->id,
                'type' => $type,
                'total_items' => count($items),
                'model_config' => $batchConfig['model']->name,
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
     * Optimize configuration for batch processing
     */
    protected function optimizeBatchConfiguration(array $items, string $type, array $options): array
    {
        $itemCount = count($items);

        // Determine optimal model based on batch size and requirements
        $requirements = [
            'task' => $type,
            'budget' => $this->determineBatchBudget($itemCount, $options),
            'quality' => $options['quality'] ?? 'standard',
            'priority' => $itemCount > 100 ? 'cost' : 'balanced',
        ];

        $model = $this->modelService->getOptimalModel($type, $requirements);

        // Determine batch size and parallelism
        $batchSize = $this->calculateOptimalBatchSize($itemCount, $model, $options);
        $parallelJobs = $this->calculateOptimalParallelism($itemCount, $options);

        return [
            'model' => $model,
            'batch_size' => $batchSize,
            'parallel_jobs' => $parallelJobs,
            'use_batch_api' => $model->hasFeature('batch_api') && $itemCount > 50,
            'cost_optimization' => $itemCount > 20,
        ];
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
                    'source' => $item['source'],
                    'type' => $item['type'] ?? $batchJob->type,
                    'options' => json_encode(array_merge($options, $item['options'] ?? [])),
                    'status' => 'queued',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert batch items
            DB::table('batch_items')->insert($batchItems);

            // Dispatch processing job for this chunk
            ProcessBatchItem::dispatch($batchJob->id, $chunkIndex, $batchConfig)
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
    public function updateBatchProgress(int $batchJobId, array $results): void
    {
        try {
            DB::beginTransaction();

            $batchJob = BatchJob::findOrFail($batchJobId);

            $successCount = count(array_filter($results, fn ($r) => $r['success']));
            $failureCount = count($results) - $successCount;

            $batchJob->increment('processed_items', count($results));
            $batchJob->increment('failed_items', $failureCount);

            // Update actual cost if provided
            $totalCost = array_sum(array_column($results, 'cost'));
            if ($totalCost > 0) {
                $batchJob->increment('actual_cost', $totalCost);
            }

            // Check if batch is complete
            if ($batchJob->processed_items >= $batchJob->total_items) {
                $batchJob->update([
                    'status' => $batchJob->failed_items > 0 ? 'completed_with_errors' : 'completed',
                    'completed_at' => now(),
                ]);

                Log::info('[BatchProcessingService] Batch processing completed', [
                    'batch_id' => $batchJobId,
                    'total_items' => $batchJob->total_items,
                    'failed_items' => $batchJob->failed_items,
                    'actual_cost' => $batchJob->actual_cost,
                    'duration' => $batchJob->completed_at->diffInSeconds($batchJob->started_at),
                ]);
            }

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('[BatchProcessingService] Failed to update batch progress', [
                'batch_id' => $batchJobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get batch processing status
     */
    public function getBatchStatus(int $batchJobId): array
    {
        $batchJob = BatchJob::findOrFail($batchJobId);

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
    public function cancelBatch(int $batchJobId): bool
    {
        try {
            $batchJob = BatchJob::findOrFail($batchJobId);

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

        // Adjust based on model capabilities
        if ($model->hasFeature('batch_api')) {
            $baseBatchSize = min(100, $baseBatchSize * 5);
        }

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

    protected function estimateBatchCost(array $items, string $type, array $options): float
    {
        $avgInputTokens = $options['avg_input_tokens'] ?? 1000;
        $avgOutputTokens = $options['avg_output_tokens'] ?? 500;

        $model = $this->modelService->getOptimalModel($type, [
            'task' => $type,
            'budget' => $this->determineBatchBudget(count($items), $options),
        ]);

        $costPerItem = $model->estimateCost($avgInputTokens, $avgOutputTokens);

        return $costPerItem * count($items);
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

        if ($batchConfig['use_batch_api']) {
            return $baseDelay; // Less delay needed with batch API
        }

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
