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

            // Process items in this chunk (sequential processing)
            $results = $this->processItemsSequentially($batchItems, $batchJob);

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

    // Removed unused batch API placeholder methods to simplify implementation

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
