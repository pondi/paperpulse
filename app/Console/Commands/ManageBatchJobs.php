<?php

namespace App\Console\Commands;

use App\Models\BatchJob;
use App\Services\BatchProcessingService;
use Illuminate\Console\Command;

class ManageBatchJobs extends Command
{
    protected $signature = 'batch:manage 
                           {action : Action (list, status, cancel, cleanup)}
                           {--id= : Batch job ID}
                           {--user= : Filter by user ID}
                           {--status= : Filter by status}
                           {--days= : Cleanup jobs older than N days}';

    protected $description = 'Manage batch processing jobs';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listBatches(),
            'status' => $this->showStatus(),
            'cancel' => $this->cancelBatch(),
            'cleanup' => $this->cleanupOldBatches(),
            default => $this->error("Unknown action: {$action}")
        };
    }

    protected function listBatches(): int
    {
        $query = BatchJob::query();

        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $batches = $query->orderBy('created_at', 'desc')->limit(20)->get();

        if ($batches->isEmpty()) {
            $this->info('No batch jobs found.');

            return self::SUCCESS;
        }

        $tableData = $batches->map(function ($batch) {
            return [
                $batch->id,
                $batch->user_id,
                $batch->type,
                $batch->status,
                "{$batch->processed_items}/{$batch->total_items}",
                number_format((float) $batch->progress_percentage, 1).'%',
                '$'.number_format((float) $batch->actual_cost, 2),
                $batch->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        $this->table([
            'ID', 'User', 'Type', 'Status', 'Progress', '%', 'Cost', 'Created',
        ], $tableData);

        return self::SUCCESS;
    }

    protected function showStatus(): int
    {
        $batchId = $this->option('id') ?? $this->ask('Batch Job ID?');

        if (! $batchId) {
            $this->error('Batch ID is required');

            return self::FAILURE;
        }

        try {
            $batchService = app(BatchProcessingService::class);
            $status = $batchService->getBatchStatus($batchId);

            $this->info("Batch Job #{$batchId} Status:");
            $this->table(['Field', 'Value'], [
                ['Status', $status['status']],
                ['Progress', $status['progress'].'%'],
                ['Total Items', $status['total_items']],
                ['Processed', $status['processed_items']],
                ['Failed', $status['failed_items']],
                ['Estimated Cost', '$'.number_format((float) $status['estimated_cost'], 4)],
                ['Actual Cost', '$'.number_format((float) $status['actual_cost'], 4)],
                ['Duration', $status['duration'].'s'],
                ['Started', $status['started_at']],
                ['Completed', $status['completed_at'] ?? 'N/A'],
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to get status: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function cancelBatch(): int
    {
        $batchId = $this->option('id') ?? $this->ask('Batch Job ID to cancel?');

        if (! $batchId) {
            $this->error('Batch ID is required');

            return self::FAILURE;
        }

        try {
            $batchService = app(BatchProcessingService::class);
            $cancelled = $batchService->cancelBatch($batchId);

            if ($cancelled) {
                $this->info("Batch job #{$batchId} cancelled successfully");

                return self::SUCCESS;
            } else {
                $this->error("Batch job #{$batchId} could not be cancelled");

                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("Failed to cancel batch: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function cleanupOldBatches(): int
    {
        $days = $this->option('days') ?? 30;

        $count = BatchJob::where('created_at', '<', now()->subDays($days))->count();

        if ($count === 0) {
            $this->info('No old batch jobs to cleanup.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Delete {$count} batch jobs older than {$days} days?")) {
            return self::SUCCESS;
        }

        $deleted = BatchJob::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} old batch jobs.");

        return self::SUCCESS;
    }
}
