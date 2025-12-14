<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
use App\Models\Receipt;
use App\Services\Receipts\Deduplication\ReceiptDeduplicator;
use App\Services\Receipts\LineItemsCreator;
use App\Services\Receipts\TotalsCalculator;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the receipt analysis pipeline and DB writes.
 *
 * Steps:
 * - Load user preferences and validate parsed data
 * - Resolve merchant, category, currency, totals, and items
 * - Create receipt + line items transactionally
 * - Emit detailed analysis logs and timing
 */
class ReceiptAnalysisRunner
{
    public function __construct(
        protected ReceiptParserContract $parser,
        protected ReceiptValidatorContract $validator,
        protected ReceiptEnricherContract $enricher,
    ) {}

    /**
     * Run the analysis pipeline and persist a new Receipt.
     *
     * @param  callable  $parseFn  Closure returning ['data' => array, ...] from parser
     * @param  string  $content  OCR text content
     * @param  array|null  $structuredData  Optional OCR structured payload
     */
    public function run(callable $parseFn, int $fileId, int $userId, string $content, ?array $structuredData = null, ?string $note = null): Receipt
    {
        $debug = config('app.debug');
        $start = microtime(true);

        ReceiptAnalysisLogger::start($fileId, $userId, $content, $structuredData);

        try {
            $prefs = UserPreferencesLoader::load($userId);
            if ($debug) {
                ReceiptAnalysisLogger::preferences($fileId, $prefs);
            }

            $analysis = $parseFn();
            [$data, $warnings] = ParsedDataValidator::validateAndSanitize($analysis['data'], $fileId, $this->validator);
            if ($debug) {
                ReceiptAnalysisLogger::dataValidated($fileId, $warnings);
            }

            DB::beginTransaction();

            $merchant = MerchantResolver::resolve($data, $this->parser, $this->enricher);
            if ($debug) {
                ReceiptAnalysisLogger::merchantProcessed($fileId, $merchant?->id, $merchant?->name);
            }

            $dateTime = DateExtractor::extract($data, $this->parser, $fileId);

            [$categoryName, $categoryId] = CategoryResolver::resolve(
                $data,
                $prefs['user'],
                $merchant,
                $this->enricher,
                $prefs['auto_categorize'],
                $prefs['default_category_id']
            );

            $currency = $this->parser->extractCurrency($data, $prefs['default_currency']);
            $items = $this->parser->extractItems($data);
            $totals = TotalsCalculator::calculate($items, $data, $this->parser);

            // Extract AI-generated summary from analysis
            $summary = $data['summary'] ?? null;

            $receiptPayload = ReceiptPayloadBuilder::build(
                $analysis,
                $data,
                $userId,
                $fileId,
                $merchant?->id,
                $merchant,
                $dateTime,
                $totals,
                $currency,
                $categoryId,
                $categoryName,
                $this->enricher,
                $prefs['default_currency'],
                $note,
                $summary
            );

            if ($debug) {
                ReceiptAnalysisLogger::creatingReceipt($fileId, $receiptPayload);
            }

            $receipt = ReceiptDeduplicator::getOrCreate($receiptPayload, $data, $this->parser);

            if ($prefs['extract_line_items']) {
                LineItemsCreator::create($receipt, $items, $data['vendors'] ?? []);
                if ($debug) {
                    ReceiptAnalysisLogger::lineItemsCreated($receipt->id, count($items));
                }
            }

            DB::commit();

            ReceiptAnalysisLogger::completed(
                $receipt->id,
                $merchant?->id,
                count($data['items'] ?? []),
                round((microtime(true) - $start) * 1000, 2),
                $structuredData
            );

            return $receipt;
        } catch (Exception $e) {
            DB::rollBack();
            ReceiptAnalysisLogger::failed($fileId, $e->getMessage(), round((microtime(true) - $start) * 1000, 2));
            throw $e;
        }
    }
}
