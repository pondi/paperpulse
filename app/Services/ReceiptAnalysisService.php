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

            // Determine category
            $categoryName = null;
            $categoryId = null;

            if ($autoCategorize && $merchant) {
                $categoryName = $this->enricher->categorizeMerchant($merchant->name);

                if ($categoryName && $user) {
                    $category = $this->enricher->findUserCategory($user, $categoryName);
                    if ($category) {
                        $categoryId = $category->id;
                    }
                }
            }

            // Use default category if no category was determined
            if (! $categoryId && $defaultCategoryId) {
                $categoryId = $defaultCategoryId;
            }

            // Extract currency and calculate totals from line items
            $currency = $this->parser->extractCurrency($data, $defaultCurrency);
            $items = $this->parser->extractItems($data);
            $totals = $this->calculateTotalsFromItems($items, $data);

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
                $this->createLineItems($receipt, $items);

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
        $calculatedTotal = 0;
        $validItemsCount = 0;
        $itemValidationErrors = [];

        // Validate and calculate total from line items
        foreach ($items as $index => $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
            $itemTotal = (float) ($item['total_price'] ?? $item['total'] ?? 0);

            // If no item total provided, calculate it
            if ($itemTotal == 0 && $unitPrice > 0) {
                $itemTotal = $quantity * $unitPrice;
            }

            // Validate item calculation
            $expectedTotal = $quantity * $unitPrice;
            if ($unitPrice > 0 && abs($itemTotal - $expectedTotal) > 0.01) {
                $itemValidationErrors[] = [
                    'item_index' => $index,
                    'item_name' => $item['name'] ?? $item['description'] ?? 'Unknown',
                    'expected_total' => $expectedTotal,
                    'actual_total' => $itemTotal,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ];
            }

            if ($itemTotal > 0) {
                $calculatedTotal += $itemTotal;
                $validItemsCount++;
            }
        }

        // Calculate tax amount from line items with VAT rates
        $calculatedTax = $this->calculateTaxFromItems($items);
        
        // Get AI totals for comparison
        $aiTotals = $this->parser->extractTotals($data);
        $aiTotal = (float) ($aiTotals['total_amount'] ?? 0);
        $aiTax = (float) ($aiTotals['tax_amount'] ?? 0);
        
        // Use calculated tax if we have VAT rates, otherwise fall back to AI tax
        $finalTax = $calculatedTax > 0 ? $calculatedTax : $aiTax;

        // Log validation results
        if (! empty($itemValidationErrors)) {
            Log::warning('[ReceiptAnalysis] Line item calculation mismatches found', [
                'validation_errors' => $itemValidationErrors,
                'total_items' => count($items),
                'valid_items' => $validItemsCount,
            ]);
        }

        // Decision logic for which total to use
        $tolerance = 0.02; // 2% tolerance for rounding differences
        if ($calculatedTotal > 0 && $validItemsCount > 0) {
            $difference = abs($calculatedTotal - $aiTotal);
            $percentDifference = $aiTotal > 0 ? ($difference / $aiTotal) : 0;

            if ($percentDifference <= $tolerance) {
                // Close enough - use AI total but log the difference
                Log::info('[ReceiptAnalysis] Using AI total - close match with calculated items', [
                    'calculated_total' => $calculatedTotal,
                    'ai_total' => $aiTotal,
                    'difference' => $difference,
                    'percent_difference' => $percentDifference * 100,
                    'valid_items_count' => $validItemsCount,
                ]);

                return [
                    'total_amount' => $aiTotal,
                    'tax_amount' => $finalTax,
                ];
            } else {
                // Significant difference - prefer calculated if we have good line items
                Log::warning('[ReceiptAnalysis] Significant difference between calculated and AI totals', [
                    'calculated_total' => $calculatedTotal,
                    'ai_total' => $aiTotal,
                    'difference' => $difference,
                    'percent_difference' => $percentDifference * 100,
                    'using' => 'calculated_total',
                    'valid_items_count' => $validItemsCount,
                ]);

                return [
                    'total_amount' => $calculatedTotal,
                    'tax_amount' => $finalTax,
                ];
            }
        }

        // Fall back to AI totals if no valid line items found
        Log::warning('[ReceiptAnalysis] Using AI totals - insufficient line item data', [
            'calculated_total' => $calculatedTotal,
            'ai_total' => $aiTotal,
            'valid_items_count' => $validItemsCount,
            'total_items' => count($items),
        ]);

        // Return AI totals but with calculated tax if available
        return [
            'total_amount' => $aiTotal,
            'tax_amount' => $finalTax,
        ];
    }

    /**
     * Calculate total tax amount from line items with VAT rates
     */
    protected function calculateTaxFromItems(array $items): float
    {
        $totalTax = 0.0;
        $taxCalculationLog = [];
        
        foreach ($items as $index => $item) {
            $vatRate = (float) ($item['vat_rate'] ?? 0);
            $itemTotal = (float) ($item['total_price'] ?? $item['total'] ?? 0);
            
            if ($vatRate > 0 && $itemTotal > 0) {
                // Norwegian VAT calculation: tax_amount = total_with_vat / (1 + vat_rate) * vat_rate
                // For example: 115 NOK with 15% VAT = 115 / 1.15 * 0.15 = 15 NOK tax
                $taxAmount = $itemTotal / (1 + $vatRate) * $vatRate;
                $totalTax += $taxAmount;
                
                $taxCalculationLog[] = [
                    'item' => $item['name'] ?? $item['description'] ?? "Item {$index}",
                    'total_with_vat' => $itemTotal,
                    'vat_rate' => $vatRate,
                    'calculated_tax' => round($taxAmount, 2),
                ];
            }
        }
        
        if ($totalTax > 0) {
            Log::info('[ReceiptAnalysis] Calculated tax from line items', [
                'total_tax' => round($totalTax, 2),
                'item_calculations' => $taxCalculationLog,
            ]);
        }
        
        return round($totalTax, 2);
    }

    /**
     * Create line items for a receipt
     */
    protected function createLineItems(Receipt $receipt, array $items): void
    {
        foreach ($items as $item) {
            LineItem::create([
                'receipt_id' => $receipt->id,
                'text' => $item['name'] ?? $item['description'] ?? 'Unknown Item',
                'sku' => $item['sku'] ?? null,
                'qty' => $item['quantity'] ?? 1,
                'price' => $item['unit_price'] ?? $item['price'] ?? 0,
                'total' => $item['total_price'] ?? $item['total'] ?? (($item['unit_price'] ?? $item['price'] ?? 0) * ($item['quantity'] ?? 1)),
            ]);
        }
    }
}
