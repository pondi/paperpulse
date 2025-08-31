<?php

namespace App\Console\Commands;

use App\Models\BatchJob;
use App\Services\AI\AIServiceFactory;
use App\Services\AI\HealthMonitoringService;
use App\Services\AI\ResilienceService;
use Illuminate\Console\Command;

class RecoverFromErrors extends Command
{
    protected $signature = 'ai:recover 
                           {action : Action (health, circuit-breakers, failed-jobs, test-providers)}
                           {--provider= : Specific provider to recover}
                           {--reprocess= : Reprocess failed items (receipts, documents, batches)}';

    protected $description = 'Recover from AI service errors and failures';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'health' => $this->checkHealth(),
            'circuit-breakers' => $this->resetCircuitBreakers(),
            'failed-jobs' => $this->reprocessFailedJobs(),
            'test-providers' => $this->testProviders(),
            default => $this->error("Unknown action: {$action}")
        };
    }

    protected function checkHealth(): int
    {
        $this->info('Checking AI system health...');

        $healthService = app(HealthMonitoringService::class);
        $health = AIServiceFactory::getSystemHealth();

        $this->info('Overall System: '.($health['overall_healthy'] ? 'Healthy' : 'Issues Detected'));

        $this->table(['Provider', 'Status', 'Circuit Breaker'],
            array_map(function ($provider, $data) {
                return [
                    $provider,
                    $data['healthy'] ? '✓ Healthy' : '✗ Issues',
                    $data['circuit_breaker_open'] ? '🔴 Open' : '🟢 Closed',
                ];
            }, array_keys($health['providers']), $health['providers'])
        );

        return self::SUCCESS;
    }

    protected function resetCircuitBreakers(): int
    {
        $provider = $this->option('provider');

        if ($provider) {
            $this->info("Resetting circuit breaker for {$provider}...");
            app(ResilienceService::class)->resetCircuitBreaker($provider);
        } else {
            $this->info('Resetting all circuit breakers...');
            AIServiceFactory::resetCircuitBreakers();
        }

        $this->info('Circuit breakers reset successfully.');

        return self::SUCCESS;
    }

    protected function reprocessFailedJobs(): int
    {
        $type = $this->option('reprocess');

        if (! $type) {
            $type = $this->choice('What to reprocess?', ['receipts', 'documents', 'batches'], 0);
        }

        $count = match ($type) {
            'receipts' => $this->reprocessFailedReceipts(),
            'documents' => $this->reprocessFailedDocuments(),
            'batches' => $this->reprocessFailedBatches(),
            default => 0
        };

        $this->info("Reprocessed {$count} failed {$type}.");

        return self::SUCCESS;
    }

    protected function reprocessFailedReceipts(): int
    {
        // Check if Receipt model exists
        if (! class_exists('\App\Models\Receipt')) {
            $this->warn('Receipt model not found. Skipping receipt reprocessing.');

            return 0;
        }

        try {
            $failedReceipts = \App\Models\Receipt::whereNull('processed_at')
                ->orWhere('status', 'failed')
                ->limit(100)
                ->get();

            $count = 0;
            foreach ($failedReceipts as $receipt) {
                try {
                    // Check if ProcessReceipt job exists
                    if (class_exists('\App\Jobs\ProcessReceipt')) {
                        \App\Jobs\ProcessReceipt::dispatch($receipt->id)
                            ->onQueue('receipts');
                        $count++;
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to requeue receipt {$receipt->id}: {$e->getMessage()}");
                }
            }

            return $count;
        } catch (\Exception $e) {
            $this->error("Error reprocessing receipts: {$e->getMessage()}");

            return 0;
        }
    }

    protected function reprocessFailedDocuments(): int
    {
        // Check if Document model exists
        if (! class_exists('\App\Models\Document')) {
            $this->warn('Document model not found. Skipping document reprocessing.');

            return 0;
        }

        try {
            $failedDocuments = \App\Models\Document::whereNull('processed_at')
                ->orWhere('status', 'failed')
                ->limit(100)
                ->get();

            $count = 0;
            foreach ($failedDocuments as $document) {
                try {
                    // Check if ProcessDocument job exists
                    if (class_exists('\App\Jobs\ProcessDocument')) {
                        \App\Jobs\ProcessDocument::dispatch($document->id)
                            ->onQueue('documents');
                        $count++;
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to requeue document {$document->id}: {$e->getMessage()}");
                }
            }

            return $count;
        } catch (\Exception $e) {
            $this->error("Error reprocessing documents: {$e->getMessage()}");

            return 0;
        }
    }

    protected function reprocessFailedBatches(): int
    {
        try {
            $failedBatches = BatchJob::where('status', 'failed')
                ->orWhere('status', 'completed_with_errors')
                ->limit(10)
                ->get();

            $count = 0;
            foreach ($failedBatches as $batch) {
                if ($this->confirm("Reprocess batch {$batch->id} with {$batch->failed_items} failed items?")) {
                    try {
                        // Reset failed items to queued status
                        $batch->items()->where('status', 'failed')->update(['status' => 'queued']);
                        $batch->update(['status' => 'queued']);

                        // Requeue batch processing if job exists
                        if (class_exists('\App\Jobs\ProcessBatchItem')) {
                            \App\Jobs\ProcessBatchItem::dispatch($batch->id, 0, [])
                                ->onQueue('batch-recovery');
                        }

                        $count++;
                    } catch (\Exception $e) {
                        $this->error("Failed to requeue batch {$batch->id}: {$e->getMessage()}");
                    }
                }
            }

            return $count;
        } catch (\Exception $e) {
            $this->error("Error reprocessing batches: {$e->getMessage()}");

            return 0;
        }
    }

    protected function testProviders(): int
    {
        $providers = ['openai', 'anthropic'];

        $this->info('Testing AI providers...');

        foreach ($providers as $provider) {
            $this->line("\nTesting {$provider}:");

            try {
                $service = AIServiceFactory::create($provider, ['task' => 'receipt']);

                $testContent = "REMA 1000\nStorgata 1\nTotalt: 100.00 kr\nDato: 2024-01-15";

                $result = $service->analyzeReceipt($testContent);

                if (is_array($result) && ($result['success'] ?? false)) {
                    $this->info("✓ {$provider} is working");
                } else {
                    $this->error("✗ {$provider} failed: ".($result['error'] ?? 'Unknown error'));
                }

            } catch (\Exception $e) {
                $this->error("✗ {$provider} error: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
