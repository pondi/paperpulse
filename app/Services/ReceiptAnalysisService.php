<?php

namespace App\Services;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
use App\Models\LineItem;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    public function analyzeAndCreateReceiptWithStructuredData(string $content, array $structuredData, int $fileId, int $userId): Receipt
    {
        $debugEnabled = config('app.debug');
        $startTime = microtime(true);

        Log::info('[ReceiptAnalysis] Starting receipt analysis with structured data', [
            'file_id' => $fileId,
            'user_id' => $userId,
            'content_length' => strlen($content),
            'forms_count' => count($structuredData['forms'] ?? []),
            'tables_count' => count($structuredData['tables'] ?? []),
            'timestamp' => now()->toISOString(),
        ]);

        try {
            // Get user's preferences
            $user = \App\Models\User::find($userId);
            $defaultCurrency = $user ? $user->preference('currency', 'NOK') : 'NOK';
            $autoCategorize = $user ? $user->preference('auto_categorize', true) : true;
            $extractLineItems = $user ? $user->preference('extract_line_items', true) : true;
            $defaultCategoryId = $user ? $user->preference('default_category_id', null) : null;

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] User preferences loaded', [
                    'file_id' => $fileId,
                    'user_id' => $userId,
                    'default_currency' => $defaultCurrency,
                    'auto_categorize' => $autoCategorize,
                    'extract_line_items' => $extractLineItems,
                    'default_category_id' => $defaultCategoryId,
                ]);
            }

            // Parse receipt using AI with structured data
            $analysis = $this->parser->parseReceiptWithStructuredData($content, $structuredData, $fileId);

            // Validate parsed data
            $validation = $this->validator->validateParsedData($analysis['data'], $fileId);

            if (! $validation['valid']) {
                throw new \Exception('Receipt data validation failed: '.implode(', ', $validation['errors']));
            }

            // Check for essential data
            if (! $this->validator->hasEssentialData($analysis['data'])) {
                throw new \Exception('AI analysis failed to extract essential merchant information');
            }

            // Sanitize the data
            $data = $this->validator->sanitizeData($analysis['data']);

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Data validated and sanitized', [
                    'file_id' => $fileId,
                    'validation_warnings' => $validation['warnings'] ?? [],
                ]);
            }

            DB::beginTransaction();

            // Extract and find/create merchant
            $merchantData = $this->parser->extractMerchantData($data);
            $merchant = $this->enricher->findOrCreateMerchant($merchantData);

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Merchant processed', [
                    'file_id' => $fileId,
                    'merchant_id' => $merchant?->id,
                    'merchant_name' => $merchant?->name,
                ]);
            }

            // Extract date and time
            $dateTime = $this->parser->extractDateTime($data);

            // Validate that we have a valid date - if not, this receipt cannot be processed
            if (! $dateTime) {
                throw new \Exception('Receipt date could not be extracted - this is required for processing');
            }

            // Determine category (prefer AI-provided)
            $categoryName = $data['merchant']['category'] ?? $data['receipt_category'] ?? null;
            $categoryId = null;

            if ($autoCategorize && !$categoryName && $merchant) {
                // Backward compatibility (deprecated heuristic)
                $categoryName = $this->enricher->categorizeMerchant($merchant->name);
            }

            if ($categoryName && $user) {
                $category = $this->enricher->findUserCategory($user, $categoryName);
                if ($category) {
                    $categoryId = $category->id;
                }
            }

            // Use default category if no category was determined
            if (! $categoryId && $defaultCategoryId) {
                $categoryId = $defaultCategoryId;
            }

            // Extract currency and calculate totals from line items
            $currency = $this->parser->extractCurrency($data, $defaultCurrency);
            $items = $this->parser->extractItems($data);
            $totals = \App\Services\Receipts\TotalsCalculator::calculate($items, $data, $this->parser);

            // Enrich receipt data
            $enrichedData = $this->enricher->enrichReceiptData($data, $merchant);

            // Create receipt
            $receiptData = [
                'user_id' => $userId,
                'file_id' => $fileId,
                'merchant_id' => $merchant?->id,
                'receipt_date' => $dateTime,
                'total_amount' => $totals['total_amount'],
                'tax_amount' => $totals['tax_amount'],
                'currency' => $currency,
                'category_id' => $categoryId,
                'receipt_category' => $categoryName,
                'receipt_description' => $this->enricher->generateEnhancedDescription($data, $defaultCurrency, $categoryName),
                'receipt_data' => json_encode(array_merge($analysis, ['enriched_data' => $enrichedData])),
            ];

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Creating receipt', [
                    'file_id' => $fileId,
                    'receipt_data_keys' => array_keys($receiptData),
                ]);
            }

            $receipt = Receipt::create($receiptData);

            // Create line items if user preference allows
            if ($extractLineItems) {
                \App\Services\Receipts\LineItemsCreator::create($receipt, $items, $data['vendors'] ?? []);

                if ($debugEnabled) {
                    Log::debug('[ReceiptAnalysis] Line items created', [
                        'receipt_id' => $receipt->id,
                        'items_count' => count($items),
                    ]);
                }
            }

            DB::commit();

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('[ReceiptAnalysis] Receipt analysis completed with structured data', [
                'receipt_id' => $receipt->id,
                'merchant_id' => $merchant?->id,
                'item_count' => count($data['items'] ?? []),
                'forms_used' => count($structuredData['forms'] ?? []),
                'tables_used' => count($structuredData['tables'] ?? []),
                'processing_time_ms' => $processingTime,
            ]);

            return $receipt;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ReceiptAnalysis] Receipt analysis failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * Analyze receipt content and create receipt with line items
     */
    public function analyzeAndCreateReceipt(string $content, int $fileId, int $userId): Receipt
    {
        $debugEnabled = config('app.debug');
        $startTime = microtime(true);

        Log::info('[ReceiptAnalysis] Starting receipt analysis', [
            'file_id' => $fileId,
            'user_id' => $userId,
            'content_length' => strlen($content),
            'timestamp' => now()->toISOString(),
        ]);

        try {
            // Get user's preferences
            $user = \App\Models\User::find($userId);
            $defaultCurrency = $user ? $user->preference('currency', 'NOK') : 'NOK';
            $autoCategorize = $user ? $user->preference('auto_categorize', true) : true;
            $extractLineItems = $user ? $user->preference('extract_line_items', true) : true;
            $defaultCategoryId = $user ? $user->preference('default_category_id', null) : null;

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] User preferences loaded', [
                    'file_id' => $fileId,
                    'user_id' => $userId,
                    'default_currency' => $defaultCurrency,
                    'auto_categorize' => $autoCategorize,
                    'extract_line_items' => $extractLineItems,
                    'default_category_id' => $defaultCategoryId,
                ]);
            }

            // Parse receipt using AI
            $analysis = $this->parser->parseReceipt($content, $fileId);

            // Validate parsed data
            $validation = $this->validator->validateParsedData($analysis['data'], $fileId);

            if (! $validation['valid']) {
                throw new \Exception('Receipt data validation failed: '.implode(', ', $validation['errors']));
            }

            // Check for essential data
            if (! $this->validator->hasEssentialData($analysis['data'])) {
                throw new \Exception('AI analysis failed to extract essential merchant information');
            }

            // Sanitize the data
            $data = $this->validator->sanitizeData($analysis['data']);

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Data validated and sanitized', [
                    'file_id' => $fileId,
                    'validation_warnings' => $validation['warnings'] ?? [],
                ]);
            }

            DB::beginTransaction();

            // Extract and find/create merchant
            $merchantData = $this->parser->extractMerchantData($data);
            $merchant = $this->enricher->findOrCreateMerchant($merchantData);

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Merchant processed', [
                    'file_id' => $fileId,
                    'merchant_id' => $merchant?->id,
                    'merchant_name' => $merchant?->name,
                ]);
            }

            // Extract date and time
            $dateTime = $this->parser->extractDateTime($data);

            // Validate that we have a valid date - if not, this receipt cannot be processed
            if (! $dateTime) {
                throw new \Exception('Receipt date could not be extracted - this is required for processing');
            }

            // Determine category (prefer AI-provided)
            $categoryName = $data['merchant']['category'] ?? $data['receipt_category'] ?? null;
            $categoryId = null;

            if ($autoCategorize && !$categoryName && $merchant) {
                // Backward compatibility (deprecated heuristic)
                $categoryName = $this->enricher->categorizeMerchant($merchant->name);
            }

            if ($categoryName && $user) {
                $category = $this->enricher->findUserCategory($user, $categoryName);
                if ($category) {
                    $categoryId = $category->id;
                }
            }

            // Use default category if no category was determined
            if (! $categoryId && $defaultCategoryId) {
                $categoryId = $defaultCategoryId;
            }

            // Extract currency and calculate totals from line items
            $currency = $this->parser->extractCurrency($data, $defaultCurrency);
            $items = $this->parser->extractItems($data);
            $totals = \App\Services\Receipts\TotalsCalculator::calculate($items, $data, $this->parser);

            // Enrich receipt data
            $enrichedData = $this->enricher->enrichReceiptData($data, $merchant);

            // Create receipt
            $receiptData = [
                'user_id' => $userId,
                'file_id' => $fileId,
                'merchant_id' => $merchant?->id,
                'receipt_date' => $dateTime,
                'total_amount' => $totals['total_amount'],
                'tax_amount' => $totals['tax_amount'],
                'currency' => $currency,
                'category_id' => $categoryId,
                'receipt_category' => $categoryName,
                'receipt_description' => $this->enricher->generateEnhancedDescription($data, $defaultCurrency, $categoryName),
                'receipt_data' => json_encode(array_merge($analysis, ['enriched_data' => $enrichedData])),
            ];

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Creating receipt', [
                    'file_id' => $fileId,
                    'receipt_data_keys' => array_keys($receiptData),
                ]);
            }

            $receipt = Receipt::create($receiptData);

            // Create line items if user preference allows
            if ($extractLineItems) {
                \App\Services\Receipts\LineItemsCreator::create($receipt, $items, $data['vendors'] ?? []);

                if ($debugEnabled) {
                    Log::debug('[ReceiptAnalysis] Line items created', [
                        'receipt_id' => $receipt->id,
                        'items_count' => count($items),
                    ]);
                }
            }

            DB::commit();

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('[ReceiptAnalysis] Receipt analysis completed', [
                'receipt_id' => $receipt->id,
                'merchant_id' => $merchant?->id,
                'item_count' => count($data['items'] ?? []),
                'processing_time_ms' => $processingTime,
            ]);

            return $receipt;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ReceiptAnalysis] Receipt analysis failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
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
            throw new \Exception('No raw text available for reanalysis');
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

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate totals from line items with validation and fallback logic
     */
    protected function calculateTotalsFromItems(array $items, array $data): array
    {
        return \App\Services\Receipts\TotalsCalculator::calculate($items, $data, $this->parser);
    }

    /**
     * Create line items for a receipt
     */
    protected function createLineItems(Receipt $receipt, array $items, array $vendors = []): void
    {
        \App\Services\Receipts\LineItemsCreator::create($receipt, $items, $vendors);
    }
}
