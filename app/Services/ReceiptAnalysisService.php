<?php

namespace App\Services;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
use App\Models\Receipt;
use App\Services\Receipts\Analysis\ReceiptAnalysisOrchestrator;
use Exception;

class ReceiptAnalysisService
{
    protected ReceiptParserContract $parser;

    protected ReceiptValidatorContract $validator;

    protected ReceiptEnricherContract $enricher;

    public function __construct(
        ReceiptParserContract $parser,
        ReceiptValidatorContract $validator,
        ReceiptEnricherContract $enricher
    ) {
        $this->parser = $parser;
        $this->validator = $validator;
        $this->enricher = $enricher;
    }

    /**
     * Analyze receipt content with structured data and create receipt with line items
     */
    public function analyzeAndCreateReceiptWithStructuredData(string $content, array $structuredData, int $fileId, int $userId, ?string $note = null): Receipt
    {
        $orchestrator = new ReceiptAnalysisOrchestrator($this->parser, $this->validator, $this->enricher);

        return $orchestrator->analyzeAndCreateReceiptWithStructuredData($content, $structuredData, $fileId, $userId, $note);
    }

    /**
     * Analyze receipt content and create receipt with line items
     */
    public function analyzeAndCreateReceipt(string $content, int $fileId, int $userId, ?string $note = null): Receipt
    {
        $orchestrator = new ReceiptAnalysisOrchestrator($this->parser, $this->validator, $this->enricher);

        return $orchestrator->analyzeAndCreateReceipt($content, $fileId, $userId, $note);
    }

    /**
     * Extract merchant information from receipt content
     */
    public function extractMerchantInfo(string $content): array
    {
        return $this->parser->extractMerchantInfo($content);
    }

    /**
     * Reanalyze an existing receipt
     */
    public function reanalyzeReceipt(Receipt $receipt): Receipt
    {
        // Get the raw text from receipt_data if available
        $rawText = null;
        if ($receipt->receipt_data && isset($receipt->receipt_data['data']['raw_text'])) {
            $rawText = $receipt->receipt_data['data']['raw_text'];
        }

        if (! $rawText) {
            throw new Exception('No raw text available for reanalysis');
        }

        // Store the original receipt information
        $originalId = $receipt->id;
        $fileId = $receipt->file_id;
        $userId = $receipt->user_id;

        DB::beginTransaction();

        try {
            // Delete existing line items
            $receipt->lineItems()->delete();

            // Delete the receipt
            $receipt->delete();

            // Reanalyze and create new receipt
            $newReceipt = $this->analyzeAndCreateReceipt(
                $rawText,
                $fileId,
                $userId
            );

            DB::commit();

            Log::info('[ReceiptAnalysis] Receipt reanalyzed', [
                'original_receipt_id' => $originalId,
                'new_receipt_id' => $newReceipt->id,
                'file_id' => $fileId,
            ]);

            return $newReceipt;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // Implementation details moved into Receipts\Analysis orchestrator and helpers
}
