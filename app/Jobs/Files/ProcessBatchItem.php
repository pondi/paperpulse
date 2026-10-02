<?php

namespace App\Jobs\Files;

use App\Models\BatchItem;
use App\Models\BatchJob;
use App\Services\AI\AIService;
use App\Services\BatchProcessingService;
use App\Services\TextExtractionService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessBatchItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    /** @param array<int, int> $itemIds */
    public function __construct(protected int $batchJobId, protected array $itemIds) {}

    public function handle(): void
    {
        $batchJob = BatchJob::withoutGlobalScope('user')->findOrFail($this->batchJobId);

        foreach ($this->itemIds as $itemId) {
            $batchJob->getConnection()->transaction(function () use ($batchJob, $itemId): void {
                $item = $batchJob->items()->whereKey($itemId)->lockForUpdate()->firstOrFail();
                if ($item->status !== 'queued') {
                    return;
                }
                if ($batchJob->fresh()->status === 'cancelled') {
                    $item->update(['status' => 'cancelled', 'processed_at' => now()]);

                    return;
                }
                $startTime = microtime(true);
                try {
                    $result = $this->processIndividualItem($item, $batchJob);
                } catch (Throwable $exception) {
                    $item->markAsFailed($exception->getMessage(), (int) ((microtime(true) - $startTime) * 1000));

                    return;
                }
                $item->markAsCompleted(
                    $result['data'],
                    $result['cost'] ?? 0,
                    (int) ((microtime(true) - $startTime) * 1000),
                );
            });
        }

        app(BatchProcessingService::class)->updateBatchProgress($this->batchJobId);
    }

    /**
     * Process individual item
     */
    protected function processIndividualItem(BatchItem $item, BatchJob $batchJob): array
    {
        $textService = app(TextExtractionService::class);
        $text = $textService->extractFromFile((int) $item->source, $batchJob->user_id, $item->type);

        if (empty(trim($text))) {
            throw new Exception('No text could be extracted from source');
        }

        // Analyze based on type
        return match ($item->type) {
            'receipt' => $this->processReceiptItem($text, $item, $batchJob),
            'document' => $this->processDocumentItem($text, $item, $batchJob),
            default => throw new Exception("Unknown batch type: {$batchJob->type}")
        };
    }

    protected function processReceiptItem(string $text, BatchItem $item, BatchJob $batchJob): array
    {
        $aiService = app(AIService::class);
        $result = $aiService->analyzeReceipt($text, $item->options);

        if (! $result['success']) {
            throw new Exception($result['error']);
        }

        return $result;
    }

    protected function processDocumentItem(string $text, BatchItem $item, BatchJob $batchJob): array
    {
        $aiService = app(AIService::class);
        $result = $aiService->analyzeDocument($text, $item->options);

        if (! $result['success']) {
            throw new Exception($result['error']);
        }

        return $result;
    }

    public function failed(Throwable $exception): void
    {
        $cancelled = BatchJob::withoutGlobalScope('user')->findOrFail($this->batchJobId)->status === 'cancelled';
        BatchItem::where('batch_job_id', $this->batchJobId)
            ->whereIn('id', $this->itemIds)
            ->where('status', 'queued')
            ->update([
                'status' => $cancelled ? 'cancelled' : 'failed',
                'error_message' => $cancelled ? null : $exception->getMessage(),
                'processed_at' => now(),
            ]);

        app(BatchProcessingService::class)->updateBatchProgress($this->batchJobId);
    }
}
