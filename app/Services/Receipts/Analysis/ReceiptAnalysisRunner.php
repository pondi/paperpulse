<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
use App\Models\File;
use App\Models\Receipt;
use App\Services\AI\PromptTemplateService;
use App\Services\AI\Shared\ProcessingStageCache;
use App\Services\AI\Shared\ProcessingUsageBudget;
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
            $file = File::where('user_id', $userId)->findOrFail($fileId);
            $prefs = UserPreferencesLoader::load($userId, $file);
            if ($debug) {
                ReceiptAnalysisLogger::preferences($fileId, $prefs);
            }

            $prompt = app(PromptTemplateService::class)->getPrompt('receipt', ['content' => '']);
            $runId = $file->meta['processing_generation'] ?? 'receipt:'.$fileId;
            $analysis = ProcessingStageCache::remember($userId, hash('sha256', $content.json_encode($structuredData)), 'legacy_receipt', [
                'prompt' => $prompt['messages'], 'schema' => $prompt['schema'], 'model' => config('ai.models.receipt'), 'options' => config('ai.options'),
            ], fn () => ProcessingUsageBudget::run($userId, $runId, 'extraction', function () use ($parseFn, $fileId): array {
                $analysis = $parseFn();
                [$sanitized, $warnings] = ParsedDataValidator::validateAndSanitize($analysis['data'] ?? [], $fileId, $this->validator);

                return [...$analysis, 'data' => $sanitized, 'processing_validation_warnings' => $warnings];
            }), $file->guid);
            $file->meta = array_merge($file->meta ?? [], ['processing_usage' => ProcessingUsageBudget::usage($userId, $runId)]);
            $file->save();
            $data = $analysis['data'];
            $warnings = $analysis['processing_validation_warnings'] ?? [];
            if ($debug) {
                ReceiptAnalysisLogger::dataValidated($fileId, $warnings);
            }

            DB::beginTransaction();

            $merchant = MerchantResolver::resolve($data, $this->parser, $this->enricher, $userId);
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
                $note
            );

            if ($debug) {
                ReceiptAnalysisLogger::creatingReceipt($fileId, $receiptPayload);
            }

            $receiptPayload = ReceiptProcessingPolicy::preserveUserValues($receiptPayload, $file);
            ReceiptProcessingPolicy::applyReview($file, $totals);
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
