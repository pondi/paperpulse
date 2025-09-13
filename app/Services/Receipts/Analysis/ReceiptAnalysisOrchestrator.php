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

class ReceiptAnalysisOrchestrator
{
    public function __construct(
        protected ReceiptParserContract $parser,
        protected ReceiptValidatorContract $validator,
        protected ReceiptEnricherContract $enricher,
    ) {
    }

    public function analyzeAndCreateReceipt(string $content, int $fileId, int $userId): Receipt
    {
        $runner = new ReceiptAnalysisRunner($this->parser, $this->validator, $this->enricher);
        return $runner->run(fn() => $this->parser->parseReceipt($content, $fileId), $fileId, $userId, $content);
    }

    public function analyzeAndCreateReceiptWithStructuredData(string $content, array $structuredData, int $fileId, int $userId): Receipt
    {
        $runner = new ReceiptAnalysisRunner($this->parser, $this->validator, $this->enricher);
        return $runner->run(fn() => $this->parser->parseReceiptWithStructuredData($content, $structuredData, $fileId), $fileId, $userId, $content, $structuredData);
    }

    // Core pipeline moved to ReceiptAnalysisRunner
}
