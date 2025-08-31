<?php

namespace App\Services;

use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Services\AI\AIService;
use App\Services\AI\AIServiceFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiptAnalysisService
{
    private AIService $aiService;

    public function __construct(?AIService $aiService = null)
    {
        $this->aiService = $aiService ?? AIServiceFactory::create();
    }

    /**
     * Analyze receipt content and create receipt with line items
     *
     * @param  string  $content  OCR text content
     * @param  int  $fileId  Associated file ID
     * @param  int  $userId  User ID
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

        if ($debugEnabled) {
            Log::debug('[ReceiptAnalysis] Content preview', [
                'file_id' => $fileId,
                'content_preview' => substr($content, 0, 300).'...',
                'content_full_length' => strlen($content),
            ]);
        }

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
                    'user_found' => $user ? true : false,
                    'default_currency' => $defaultCurrency,
                    'auto_categorize' => $autoCategorize,
                    'extract_line_items' => $extractLineItems,
                    'default_category_id' => $defaultCategoryId,
                ]);
            }

            // Analyze receipt using AI
            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Calling AI service', [
                    'file_id' => $fileId,
                    'ai_service_class' => get_class($this->aiService),
                    'content_length' => strlen($content),
                ]);
            }

            $analysis = $this->aiService->analyzeReceipt($content);

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] AI service response', [
                    'file_id' => $fileId,
                    'analysis_success' => $analysis['success'] ?? false,
                    'provider' => $analysis['provider'] ?? 'unknown',
                    'model' => $analysis['model'] ?? 'unknown',
                    'tokens_used' => $analysis['tokens_used'] ?? 0,
                    'fallback_used' => $analysis['fallback_used'] ?? false,
                    'template' => $analysis['template'] ?? 'unknown',
                    'data_keys' => isset($analysis['data']) && is_array($analysis['data']) ? array_keys($analysis['data']) : 'no data',
                    'error' => $analysis['error'] ?? null,
                ]);
            }

            if (! $analysis['success']) {
                throw new \Exception($analysis['error'] ?? 'Receipt analysis failed');
            }

            // Validate that we have essential data - fail if not
            $data = $analysis['data'];
            $hasValidMerchant = ! empty($data['merchant']['name']) || ! empty($data['store']['name']);
            $hasValidTotal = ! empty($data['totals']['total_amount']) || ! empty($data['receipt']['total']) || ! empty($data['total']);

            if (! $hasValidMerchant) {
                throw new \Exception('AI analysis failed to extract merchant information');
            }

            if (! $hasValidTotal) {
                Log::warning('[ReceiptAnalysis] AI analysis missing total amount', [
                    'file_id' => $fileId,
                    'data_structure' => array_keys($data),
                    'analysis_provider' => $analysis['provider'] ?? 'unknown',
                    'fallback_used' => $analysis['fallback_used'] ?? false,
                ]);
                // Don't fail for missing total, but log it as a warning
            }

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Extracted data structure', [
                    'file_id' => $fileId,
                    'merchant_data' => $data['merchant'] ?? 'not found',
                    'totals_data' => $data['totals'] ?? 'not found',
                    'receipt_info_data' => $data['receipt_info'] ?? 'not found',
                    'items_count' => isset($data['items']) && is_array($data['items']) ? count($data['items']) : 0,
                    'payment_data' => $data['payment'] ?? 'not found',
                ]);
            }

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Starting database transaction', [
                    'file_id' => $fileId,
                ]);
            }

            DB::beginTransaction();

            // Find or create merchant - flexible data structure handling
            $merchantData = $this->extractMerchantData($data);

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Processing merchant data', [
                    'file_id' => $fileId,
                    'merchant_data' => $merchantData,
                ]);
            }

            $merchant = $this->findOrCreateMerchant($merchantData);

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Merchant processed', [
                    'file_id' => $fileId,
                    'merchant_id' => $merchant?->id,
                    'merchant_name' => $merchant?->name,
                    'merchant_created' => $merchant?->wasRecentlyCreated ?? false,
                ]);
            }

            // Parse date and time - flexible data structure
            $dateTime = $this->extractDateTime($data);

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Date/time parsed', [
                    'file_id' => $fileId,
                    'raw_date' => $data['receipt_info']['date'] ?? $data['date'] ?? null,
                    'raw_time' => $data['receipt_info']['time'] ?? $data['time'] ?? null,
                    'parsed_datetime' => $dateTime?->toISOString(),
                ]);
            }

            // Determine category
            $categoryName = null;
            $categoryId = null;

            if ($autoCategorize) {
                $categoryName = $this->categorizeMerchant($merchant?->name ?? '');

                if ($debugEnabled) {
                    Log::debug('[ReceiptAnalysis] Auto-categorization', [
                        'file_id' => $fileId,
                        'merchant_name' => $merchant?->name ?? '',
                        'suggested_category' => $categoryName,
                    ]);
                }

                // Try to find matching category by name
                if ($categoryName && $user) {
                    $category = $user->categories()
                        ->where('name', 'LIKE', $categoryName)
                        ->first();
                    if ($category) {
                        $categoryId = $category->id;
                    }

                    if ($debugEnabled) {
                        Log::debug('[ReceiptAnalysis] Category matching', [
                            'file_id' => $fileId,
                            'suggested_category' => $categoryName,
                            'matched_category_id' => $categoryId,
                            'matched_category_name' => $category?->name,
                        ]);
                    }
                }
            }

            // Use default category if no category was determined
            if (! $categoryId && $defaultCategoryId) {
                $categoryId = $defaultCategoryId;

                if ($debugEnabled) {
                    Log::debug('[ReceiptAnalysis] Using default category', [
                        'file_id' => $fileId,
                        'default_category_id' => $defaultCategoryId,
                    ]);
                }
            }

            // Create receipt - flexible data extraction
            $totals = $this->extractTotals($data);

            $receiptData = [
                'user_id' => $userId,
                'file_id' => $fileId,
                'merchant_id' => $merchant?->id,
                'receipt_date' => $dateTime,
                'total_amount' => $totals['total_amount'],
                'tax_amount' => $totals['tax_amount'],
                'currency' => $this->extractCurrency($data, $defaultCurrency),
                'category_id' => $categoryId,
                'receipt_category' => $categoryName,
                'receipt_description' => $this->generateDescription($data, $defaultCurrency),
                'receipt_data' => json_encode($analysis),
            ];

            if ($debugEnabled) {
                Log::debug('[ReceiptAnalysis] Creating receipt', [
                    'file_id' => $fileId,
                    'receipt_data' => array_merge($receiptData, [
                        'receipt_data' => '[JSON_ANALYSIS_DATA]', // Don't log the full analysis data
                    ]),
                ]);
            }

            $receipt = Receipt::create($receiptData);

            // Create line items if user preference allows - flexible data structure
            if ($extractLineItems) {
                $items = $this->extractItems($data);
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

            DB::commit();

            Log::info('Receipt analysis completed', [
                'receipt_id' => $receipt->id,
                'merchant_id' => $merchant?->id,
                'item_count' => count($data['items'] ?? []),
            ]);

            return $receipt;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Receipt analysis failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            throw $e;
        }
    }

    /**
     * Extract merchant information from receipt content
     */
    public function extractMerchantInfo(string $content): array
    {
        return $this->aiService->extractMerchant($content);
    }

    /**
     * Find or create merchant based on extracted data
     */
    private function findOrCreateMerchant(array $merchantData): ?Merchant
    {
        if (empty($merchantData['name'])) {
            return null;
        }

        // Try to find existing merchant by name or org number
        $query = Merchant::query();

        if (! empty($merchantData['org_number'])) {
            $query->orWhere('vat_number', $merchantData['org_number']);
        }

        $query->orWhere('name', 'ILIKE', '%'.$merchantData['name'].'%');

        $merchant = $query->first();

        if ($merchant) {
            // Update merchant data if we have new information
            $merchant->update(array_filter([
                'address' => $merchantData['address'] ?? $merchant->address,
                'phone' => $merchantData['phone'] ?? $merchant->phone,
                'vat_number' => $merchantData['org_number'] ?? $merchant->vat_number,
            ]));

            return $merchant;
        }

        // Create new merchant
        return Merchant::create([
            'name' => $merchantData['name'],
            'address' => $merchantData['address'] ?? null,
            'phone' => $merchantData['phone'] ?? null,
            'vat_number' => $merchantData['org_number'] ?? null,
            'type' => $this->categorizeMerchant($merchantData['name']),
            'status' => 'active',
        ]);
    }

    /**
     * Parse date and time from receipt data
     */
    private function parseDateTime(?string $date, ?string $time): Carbon
    {
        try {
            if ($date && $time) {
                return Carbon::parse("$date $time");
            } elseif ($date) {
                return Carbon::parse($date);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse receipt date/time', [
                'date' => $date,
                'time' => $time,
                'error' => $e->getMessage(),
            ]);
        }

        return Carbon::now();
    }

    /**
     * Categorize merchant based on name
     */
    private function categorizeMerchant(string $name): string
    {
        $name = strtolower($name);

        $categories = [
            'grocery' => ['rema', 'kiwi', 'coop', 'bunnpris', 'meny', 'spar', 'joker'],
            'restaurant' => ['restaurant', 'cafe', 'coffee', 'pizza', 'burger', 'sushi'],
            'fuel' => ['shell', 'esso', 'circle k', 'uno-x', 'best'],
            'pharmacy' => ['apotek', 'vitusapotek', 'boots'],
            'electronics' => ['elkjøp', 'power', 'komplett'],
            'clothing' => ['h&m', 'zara', 'dressmann', 'cubus'],
            'hardware' => ['jernia', 'byggmakker', 'maxbo', 'xl-bygg'],
            'transport' => ['ruter', 'vy', 'taxi', 'uber'],
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return $category;
                }
            }
        }

        return 'other';
    }

    /**
     * Categorize line item based on description
     */
    private function categorizeItem(string $description): ?string
    {
        $description = strtolower($description);

        $categories = [
            'food' => ['brød', 'melk', 'egg', 'frukt', 'grønnsaker', 'kjøtt'],
            'beverage' => ['kaffe', 'te', 'juice', 'vann', 'øl', 'vin'],
            'household' => ['papir', 'såpe', 'vask', 'rens'],
            'personal_care' => ['shampoo', 'deodorant', 'tannkrem'],
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($description, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Generate description from receipt data
     */
    private function generateDescription(array $data, string $defaultCurrency = 'NOK'): string
    {
        $parts = [];

        if (! empty($data['merchant']['name'])) {
            $parts[] = 'Purchase at '.$data['merchant']['name'];
        }

        if (! empty($data['items'])) {
            $parts[] = count($data['items']).' items';
        }

        if (! empty($data['totals']['total_amount']) || ! empty($data['totals']['total'])) {
            $total = $data['totals']['total_amount'] ?? $data['totals']['total'];
            $currency = $data['payment']['currency'] ?? $data['currency'] ?? $defaultCurrency;
            $parts[] = 'Total: '.number_format($total, 2).' '.$currency;
        }

        return implode(', ', $parts) ?: 'Receipt';
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

        // Store the original receipt ID and data
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

            return $newReceipt;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Extract merchant data from flexible AI response structure
     */
    private function extractMerchantData(array $data): array
    {
        // Try different possible structures - now including nested receipt structure
        if (! empty($data['merchant'])) {
            return $data['merchant'];
        }

        if (! empty($data['store'])) {
            return [
                'name' => $data['store']['name'] ?? '',
                'address' => $data['store']['address'] ?? '',
                'org_number' => $data['store']['organization_number'] ?? '',
            ];
        }

        // Fallback to root level data
        return [
            'name' => $data['merchant_name'] ?? $data['store_name'] ?? '',
            'address' => $data['merchant_address'] ?? $data['store_address'] ?? '',
            'org_number' => $data['org_number'] ?? $data['organization_number'] ?? '',
        ];
    }

    /**
     * Extract date/time from flexible AI response structure
     */
    private function extractDateTime(array $data): Carbon
    {
        $date = null;
        $time = null;

        // Try different possible structures - now including nested receipt structure
        if (! empty($data['receipt_info'])) {
            $date = $data['receipt_info']['date'] ?? null;
            $time = $data['receipt_info']['time'] ?? null;
        } elseif (! empty($data['receipt']) && is_array($data['receipt'])) {
            // Handle nested receipt structure from fallback response
            $receiptData = $data['receipt'];
            $date = $receiptData['date'] ?? null;
            $time = $receiptData['time'] ?? null;
        } elseif (! empty($data['transaction'])) {
            $date = $data['transaction']['date'] ?? null;
            $time = $data['transaction']['time'] ?? null;
        } else {
            $date = $data['date'] ?? null;
            $time = $data['time'] ?? null;
        }

        return $this->parseDateTime($date, $time);
    }

    /**
     * Extract totals from flexible AI response structure
     */
    private function extractTotals(array $data): array
    {
        $totalAmount = 0;
        $taxAmount = 0;

        // Try different possible structures - now including nested receipt structure
        if (! empty($data['totals'])) {
            $totalAmount = $data['totals']['total_amount'] ??
                          $data['totals']['total'] ??
                          $data['totals']['gross_amount'] ?? 0;
            $taxAmount = $data['totals']['tax_amount'] ??
                        $data['totals']['vat_amount'] ??
                        $data['totals']['tax'] ?? 0;
        } elseif (! empty($data['receipt']) && is_array($data['receipt'])) {
            // Handle nested receipt structure from fallback response
            $receiptData = $data['receipt'];
            $totalAmount = $receiptData['total'] ?? 0;

            // Calculate tax from Norwegian VAT structure
            if (! empty($receiptData['vat']) && is_array($receiptData['vat'])) {
                foreach ($receiptData['vat'] as $vatEntry) {
                    if (isset($vatEntry['vat_amount']) && is_numeric($vatEntry['vat_amount'])) {
                        $taxAmount += (float) $vatEntry['vat_amount'];
                    }
                }
            }
        } else {
            $totalAmount = $data['total_amount'] ?? $data['total'] ?? 0;
            $taxAmount = $data['tax_amount'] ?? $data['vat_amount'] ?? 0;
        }

        return [
            'total_amount' => (float) $totalAmount,
            'tax_amount' => (float) $taxAmount,
        ];
    }

    /**
     * Extract currency from flexible AI response structure
     */
    private function extractCurrency(array $data, string $default = 'NOK'): string
    {
        return $data['payment']['currency'] ??
               $data['currency'] ??
               $data['totals']['currency'] ??
               $default;
    }

    /**
     * Extract items from flexible AI response structure
     */
    private function extractItems(array $data): array
    {
        // Check for nested receipt structure first (fallback response)
        if (! empty($data['receipt']) && is_array($data['receipt']) && ! empty($data['receipt']['items'])) {
            return $data['receipt']['items'];
        }

        // Fallback to direct structure
        return $data['items'] ?? $data['line_items'] ?? [];
    }
}
