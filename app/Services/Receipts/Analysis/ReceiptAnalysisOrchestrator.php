<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
use App\Models\Receipt;
use App\Services\Receipts\LineItemsCreator;
use App\Services\Receipts\TotalsCalculator;
use App\Services\Receipts\Analysis\ParsedDataValidator;
use App\Services\Receipts\Analysis\MerchantResolver;
use App\Services\Receipts\Analysis\ReceiptPayloadBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Thin orchestrator for running receipt analysis using the runner.
 *
 * Delegates the full pipeline to ReceiptAnalysisRunner while exposing
 * simple methods for text-only and text+structured data flows.
 */
class ReceiptAnalysisOrchestrator
{
    public function __construct(
        protected ReceiptParserContract $parser,
        protected ReceiptValidatorContract $validator,
        protected ReceiptEnricherContract $enricher,
    ) {
    }

    /**
     * Analyze text content and create a Receipt.
     *
     * @param string $content OCR-extracted text
     * @param int $fileId
     * @param int $userId
     * @return Receipt
     */
    public function analyzeAndCreateReceipt(string $content, int $fileId, int $userId): Receipt
    {
        $runner = new ReceiptAnalysisRunner($this->parser, $this->validator, $this->enricher);
        return $runner->run(fn() => $this->parser->parseReceipt($content, $fileId), $fileId, $userId, $content);
    }

    /**
     * Analyze text + structured OCR data and create a Receipt.
     *
     * @param string $content OCR-extracted text
     * @param array $structuredData Provider-specific OCR structured payload
     * @param int $fileId
     * @param int $userId
     * @return Receipt
     */
    public function analyzeAndCreateReceiptWithStructuredData(string $content, array $structuredData, int $fileId, int $userId): Receipt
    {
        $runner = new ReceiptAnalysisRunner($this->parser, $this->validator, $this->enricher);
        return $runner->run(fn() => $this->parser->parseReceiptWithStructuredData($content, $structuredData, $fileId), $fileId, $userId, $content, $structuredData);
    }

    // Core pipeline moved to ReceiptAnalysisRunner
}
