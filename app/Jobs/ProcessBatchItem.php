<?php

namespace App\Jobs;

use App\Models\BatchItem;
use App\Models\BatchJob;
use App\Services\AI\AIServiceFactory;
use App\Services\BatchProcessingService;
use App\Services\TextExtractionService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessBatchItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $batchJobId;

    protected int $chunkIndex;

    protected array $batchConfig;

    public $timeout = 300; // 5 minutes

    public $tries = 3;

    public function __construct(int $batchJobId, int $chunkIndex, array $batchConfig)
    {
        $this->batchJobId = $batchJobId;
        $this->chunkIndex = $chunkIndex;
        $this->batchConfig = $batchConfig;
    }

    public function handle(): void
    {
        try {
            $batchJob = BatchJob::findOrFail($this->batchJobId);

            // Get batch items for this chunk
            $batchItems = BatchItem::where('batch_job_id', $this->batchJobId)
                ->where('status', 'queued')
                ->orderBy('item_index')
                ->skip($this->chunkIndex * $this->batchConfig['batch_size'])
                ->take($this->batchConfig['batch_size'])
                ->get();

            if ($batchItems->isEmpty()) {
                Log::warning('[ProcessBatchItem] No items found for chunk', [
                    'batch_id' => $this->batchJobId,
                    'chunk_index' => $this->chunkIndex,
                ]);

                return;
            }

            Log::info('[ProcessBatchItem] Processing batch chunk', [
                'batch_id' => $this->batchJobId,
                'chunk_index' => $this->chunkIndex,
                'items_count' => $batchItems->count(),
                'model' => $this->batchConfig['model']->name,
            ]);

            // Process items in this chunk
            $results = [];

            if ($this->batchConfig['use_batch_api']) {
                $results = $this->processBatchWithAPI($batchItems, $batchJob);
            } else {
                $results = $this->processItemsSequentially($batchItems, $batchJob);
            }

            // Update batch progress
            $batchService = app(BatchProcessingService::class);
            $batchService->updateBatchProgress($this->batchJobId, $results);

        } catch (Exception $e) {
            Log::error('[ProcessBatchItem] Batch chunk processing failed', [
                'batch_id' => $this->batchJobId,
                'chunk_index' => $this->chunkIndex,
                'error' => $e->getMessage(),
            ]);

            // Mark all items in this chunk as failed
            $this->markChunkAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Process items using batch API (when available)
     */
    protected function processBatchWithAPI(Collection $batchItems, BatchJob $batchJob): array
    {
        try {
            // Prepare batch request
            $batchRequests = [];
            foreach ($batchItems as $item) {
                $batchRequests[] = $this->prepareBatchRequest($item, $batchJob);
            }

            // Execute batch request
            $aiService = AIServiceFactory::create($this->batchConfig['model']->provider);
            $batchResults = $this->executeBatchRequest($aiService, $batchRequests);

            // Process results
            $results = [];
            foreach ($batchItems as $index => $item) {
                $result = $batchResults[$index] ?? null;

                if ($result && $result['success']) {
                    $item->markAsCompleted(
                        $result['data'],
                        $result['cost'] ?? 0,
                        $result['processing_time'] ?? 0
                    );
                    $results[] = $result;
                } else {
                    $error = $result['error'] ?? 'Batch processing failed';
                    $item->markAsFailed($error);
                    $results[] = ['success' => false, 'error' => $error];
                }
            }

            return $results;

        } catch (Exception $e) {
            Log::error('[ProcessBatchItem] Batch API processing failed', [
                'error' => $e->getMessage(),
                'items_count' => $batchItems->count(),
            ]);

            // Fallback to sequential processing
            return $this->processItemsSequentially($batchItems, $batchJob);
        }
    }

    /**
     * Process items one by one
     */
    protected function processItemsSequentially(Collection $batchItems, BatchJob $batchJob): array
    {
        $results = [];

        foreach ($batchItems as $item) {
            $startTime = microtime(true);

            try {
                $result = $this->processIndividualItem($item, $batchJob);
                $processingTime = (int) ((microtime(true) - $startTime) * 1000);

                $item->markAsCompleted(
                    $result['data'],
                    $result['cost'] ?? 0,
                    $processingTime
                );

                $results[] = array_merge($result, [
                    'processing_time' => $processingTime,
                    'success' => true,
                ]);

            } catch (Exception $e) {
                $processingTime = (int) ((microtime(true) - $startTime) * 1000);

                $item->markAsFailed($e->getMessage(), $processingTime);

                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'processing_time' => $processingTime,
                ];
            }

            // Add small delay between items to avoid rate limiting
            if (count($results) < $batchItems->count()) {
                usleep(100000); // 100ms delay
            }
        }

        return $results;
    }

    /**
     * Process individual item
     */
    protected function processIndividualItem(BatchItem $item, BatchJob $batchJob): array
    {
        $textService = app(TextExtractionService::class);
        $options = $item->options;

        // Extract text
        $text = $textService->extractFromSource($item->source, $options);

        if (empty(trim($text))) {
            throw new Exception('No text could be extracted from source');
        }

        // Analyze based on type
        return match ($batchJob->type) {
            'receipt' => $this->processReceiptItem($text, $item, $batchJob),
            'document' => $this->processDocumentItem($text, $item, $batchJob),
            default => throw new Exception("Unknown batch type: {$batchJob->type}")
        };
    }

    protected function processReceiptItem(string $text, BatchItem $item, BatchJob $batchJob): array
    {
        $aiService = AIServiceFactory::create($this->batchConfig['model']->provider);
        $result = $aiService->analyzeReceipt($text, $item->options);

        if (! $result['success']) {
            throw new Exception($result['error']);
        }

        return $result;
    }

    protected function processDocumentItem(string $text, BatchItem $item, BatchJob $batchJob): array
    {
        $aiService = AIServiceFactory::create($this->batchConfig['model']->provider);
        $result = $aiService->analyzeDocument($text, $item->options);

        if (! $result['success']) {
            throw new Exception($result['error']);
        }

        return $result;
    }

    protected function prepareBatchRequest(BatchItem $item, BatchJob $batchJob): array
    {
        // Prepare request for batch API
        return [
            'custom_id' => "item_{$item->id}",
            'method' => 'POST',
            'url' => $this->getBatchEndpoint($batchJob->type),
            'body' => $this->getBatchRequestBody($item, $batchJob),
        ];
    }

    protected function executeBatchRequest($aiService, array $requests): array
    {
        // This would use provider-specific batch APIs
        // Implementation depends on provider capabilities
        throw new Exception('Batch API not yet implemented');
    }

    protected function getBatchEndpoint(string $type): string
    {
        return match ($type) {
            'receipt' => '/v1/chat/completions',
            'document' => '/v1/chat/completions',
            default => '/v1/chat/completions'
        };
    }

    protected function getBatchRequestBody(BatchItem $item, BatchJob $batchJob): array
    {
        // This would prepare the request body based on the item and job type
        return [
            'model' => $this->batchConfig['model']->name,
            'messages' => [
                ['role' => 'user', 'content' => $item->source],
            ],
        ];
    }

    protected function markChunkAsFailed(string $error): void
    {
        BatchItem::where('batch_job_id', $this->batchJobId)
            ->where('status', 'queued')
            ->skip($this->chunkIndex * $this->batchConfig['batch_size'])
            ->take($this->batchConfig['batch_size'])
            ->update([
                'status' => 'failed',
                'error_message' => $error,
                'processed_at' => now(),
            ]);
    }

    public function failed(Exception $exception): void
    {
        Log::error('[ProcessBatchItem] Job failed', [
            'batch_id' => $this->batchJobId,
            'chunk_index' => $this->chunkIndex,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->markChunkAsFailed($exception->getMessage());
    }
}
