<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
use App\Models\Receipt;/**
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
    ) {}

    /**
     * Analyze text content and create a Receipt.
     *
     * @param  string  $content  OCR-extracted text
     */
    public function analyzeAndCreateReceipt(string $content, int $fileId, int $userId, ?string $note = null): Receipt
    {
        $runner = new ReceiptAnalysisRunner($this->parser, $this->validator, $this->enricher);

        return $runner->run(
            fn () => $this->parser->parseReceipt($content, $fileId),
            $fileId,
            $userId,
            $content,
            null,
            $note
        );
    }

    /**
     * Analyze text + structured OCR data and create a Receipt.
     *
     * @param  string  $content  OCR-extracted text
     * @param  array  $structuredData  Provider-specific OCR structured payload
     */
    public function analyzeAndCreateReceiptWithStructuredData(string $content, array $structuredData, int $fileId, int $userId, ?string $note = null): Receipt
    {
        $runner = new ReceiptAnalysisRunner($this->parser, $this->validator, $this->enricher);

        return $runner->run(
            fn () => $this->parser->parseReceiptWithStructuredData($content, $structuredData, $fileId),
            $fileId,
            $userId,
            $content,
            $structuredData,
            $note
        );
    }

    // Core pipeline moved to ReceiptAnalysisRunner
}
