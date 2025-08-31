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
        Log::info('Starting receipt analysis', [
            'file_id' => $fileId,
            'user_id' => $userId,
            'content_length' => strlen($content),
        ]);

        try {
            // Get user's preferences
            $user = \App\Models\User::find($userId);
            $defaultCurrency = $user ? $user->preference('currency', 'NOK') : 'NOK';
            $autoCategorize = $user ? $user->preference('auto_categorize', true) : true;
            $extractLineItems = $user ? $user->preference('extract_line_items', true) : true;
            $defaultCategoryId = $user ? $user->preference('default_category_id', null) : null;

            // Analyze receipt using AI
            $analysis = $this->aiService->analyzeReceipt($content);

            if (! $analysis['success']) {
                throw new \Exception($analysis['error'] ?? 'Receipt analysis failed');
            }

            $data = $analysis['data'];

            DB::beginTransaction();

            // Find or create merchant
            $merchant = $this->findOrCreateMerchant($data['merchant'] ?? []);

            // Parse date and time
            $dateTime = $this->parseDateTime(
                $data['date'] ?? null,
                $data['time'] ?? null
            );

            // Determine category
            $categoryName = null;
            $categoryId = null;

            if ($autoCategorize) {
                $categoryName = $this->categorizeMerchant($merchant?->name ?? '');
                // Try to find matching category by name
                if ($categoryName && $user) {
                    $category = $user->categories()
                        ->where('name', 'LIKE', $categoryName)
                        ->first();
                    if ($category) {
                        $categoryId = $category->id;
                    }
                }
            }

            // Use default category if no category was determined
            if (! $categoryId && $defaultCategoryId) {
                $categoryId = $defaultCategoryId;
            }

            // Create receipt
            $receipt = Receipt::create([
                'user_id' => $userId,
                'file_id' => $fileId,
                'merchant_id' => $merchant?->id,
                'receipt_date' => $dateTime,
                'total_amount' => $data['totals']['total'] ?? 0,
                'tax_amount' => $data['totals']['tax'] ?? 0,
                'currency' => $data['currency'] ?? $defaultCurrency,
                'category_id' => $categoryId,
                'receipt_category' => $categoryName,
                'receipt_description' => $this->generateDescription($data, $defaultCurrency),
                'receipt_data' => json_encode($analysis),
            ]);

            // Create line items if user preference allows
            if ($extractLineItems && ! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    LineItem::create([
                        'receipt_id' => $receipt->id,
                        'text' => $item['name'] ?? 'Unknown Item',
                        'sku' => $item['sku'] ?? null,
                        'qty' => $item['quantity'] ?? 1,
                        'price' => $item['price'] ?? 0,
                        'total' => $item['total'] ?? ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
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
     * Extract line items from receipt content
     */
    public function extractLineItems(string $content): array
    {
        return $this->aiService->extractLineItems($content);
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

        if (! empty($data['totals']['total'])) {
            $parts[] = 'Total: '.number_format($data['totals']['total'], 2).' '.($data['currency'] ?? $defaultCurrency);
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
}
