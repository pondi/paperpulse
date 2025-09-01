<?php

namespace App\Services\Receipt;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ReceiptEnricherService implements ReceiptEnricherContract
{
    /**
     * Find or create merchant based on extracted data
     */
    public function findOrCreateMerchant(array $merchantData): ?Merchant
    {
        if (empty($merchantData['name'])) {
            return null;
        }

        Log::debug('[ReceiptEnricher] Looking for merchant', [
            'merchant_name' => $merchantData['name'],
            'org_number' => $merchantData['org_number'] ?? null,
        ]);

        $query = Merchant::query();

        if (! empty($merchantData['org_number'])) {
            $existingMerchant = $query->where('org_number', $merchantData['org_number'])->first();
            if ($existingMerchant) {
                Log::debug('[ReceiptEnricher] Found merchant by org number', [
                    'merchant_id' => $existingMerchant->id,
                    'org_number' => $merchantData['org_number'],
                ]);

                return $existingMerchant;
            }
        }

        $existingMerchant = Merchant::where('name', 'LIKE', '%'.$merchantData['name'].'%')->first();
        if ($existingMerchant) {
            Log::debug('[ReceiptEnricher] Found merchant by name similarity', [
                'merchant_id' => $existingMerchant->id,
                'merchant_name' => $existingMerchant->name,
                'searched_name' => $merchantData['name'],
            ]);

            return $existingMerchant;
        }

        $merchant = new Merchant;
        $merchant->name = $merchantData['name'];
        $merchant->address = $merchantData['address'] ?? null;
        $merchant->org_number = $merchantData['org_number'] ?? null;
        $merchant->save();

        Log::info('[ReceiptEnricher] Created new merchant', [
            'merchant_id' => $merchant->id,
            'merchant_name' => $merchant->name,
            'org_number' => $merchant->org_number,
        ]);

        return $merchant;
    }

    /**
     * Categorize merchant based on name
     */
    public function categorizeMerchant(string $merchantName): ?string
    {
        if (empty($merchantName)) {
            return null;
        }

        $merchantName = strtolower($merchantName);

        $categories = [
            'Groceries' => [
                'keywords' => ['grocery', 'supermarket', 'market', 'food', 'ica', 'coop', 'rema', 'kiwi', 'meny'],
                'patterns' => ['/food.*store/', '/super.*market/'],
            ],
            'Restaurant' => [
                'keywords' => ['restaurant', 'cafe', 'pizza', 'burger', 'sushi', 'bar', 'pub', 'bistro', 'eatery', 'mcdonald'],
                'patterns' => ['/.*restaurant/', '/.*cafe/'],
            ],
            'Gas Station' => [
                'keywords' => ['shell', 'esso', 'statoil', 'circle k', 'uno-x', 'gas', 'fuel', 'petrol'],
                'patterns' => ['/.*gas.*station/', '/.*fuel/'],
            ],
            'Pharmacy' => [
                'keywords' => ['pharmacy', 'apotek', 'boots', 'medicine', 'drug store'],
                'patterns' => ['/.*pharmacy/', '/.*apotek/'],
            ],
            'Clothing' => [
                'keywords' => ['h&m', 'zara', 'nike', 'adidas', 'clothing', 'fashion', 'apparel'],
                'patterns' => ['/.*clothing/', '/.*fashion/'],
            ],
            'Electronics' => [
                'keywords' => ['elkjøp', 'mediamarkt', 'komplett', 'apple', 'samsung', 'electronics'],
                'patterns' => ['/.*electronics/', '/.*tech/'],
            ],
            'Transportation' => [
                'keywords' => ['ruter', 'nsb', 'vy', 'taxi', 'uber', 'bolt', 'transport', 'bus', 'train'],
                'patterns' => ['/.*transport/', '/.*taxi/'],
            ],
            'Entertainment' => [
                'keywords' => ['cinema', 'movie', 'theater', 'concert', 'entertainment', 'spotify', 'netflix'],
                'patterns' => ['/.*cinema/', '/.*entertainment/'],
            ],
            'Health' => [
                'keywords' => ['hospital', 'clinic', 'doctor', 'dental', 'health', 'medical'],
                'patterns' => ['/.*medical/', '/.*health/'],
            ],
            'Home & Garden' => [
                'keywords' => ['ikea', 'bauhaus', 'maxbo', 'home', 'garden', 'furniture', 'hardware'],
                'patterns' => ['/.*furniture/', '/.*garden/'],
            ],
        ];

        foreach ($categories as $category => $rules) {
            // Check keywords
            foreach ($rules['keywords'] as $keyword) {
                if (strpos($merchantName, $keyword) !== false) {
                    Log::debug('[ReceiptEnricher] Category matched by keyword', [
                        'merchant_name' => $merchantName,
                        'category' => $category,
                        'keyword' => $keyword,
                    ]);

                    return $category;
                }
            }

            // Check patterns
            foreach ($rules['patterns'] as $pattern) {
                if (preg_match($pattern, $merchantName)) {
                    Log::debug('[ReceiptEnricher] Category matched by pattern', [
                        'merchant_name' => $merchantName,
                        'category' => $category,
                        'pattern' => $pattern,
                    ]);

                    return $category;
                }
            }
        }

        Log::debug('[ReceiptEnricher] No category matched', [
            'merchant_name' => $merchantName,
        ]);

        return null;
    }

    /**
     * Find user's category by name
     */
    public function findUserCategory(User $user, string $categoryName): ?\App\Models\Category
    {
        if (empty($categoryName)) {
            return null;
        }

        $category = $user->categories()
            ->where('name', 'LIKE', $categoryName)
            ->first();

        if ($category) {
            Log::debug('[ReceiptEnricher] Found user category', [
                'user_id' => $user->id,
                'category_id' => $category->id,
                'category_name' => $category->name,
                'searched_name' => $categoryName,
            ]);
        }

        return $category;
    }

    /**
     * Enrich receipt data with additional information
     */
    public function enrichReceiptData(array $receiptData, ?Merchant $merchant = null): array
    {
        // Add merchant category if merchant is provided
        if ($merchant && ! empty($merchant->name)) {
            $suggestedCategory = $this->categorizeMerchant($merchant->name);
            if ($suggestedCategory) {
                $receiptData['suggested_category'] = $suggestedCategory;
            }
        }

        // Add merchant information
        if ($merchant) {
            $receiptData['merchant_info'] = [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'address' => $merchant->address,
                'org_number' => $merchant->org_number,
            ];
        }

        // Analyze spending patterns (could be expanded)
        $receiptData['spending_analysis'] = $this->analyzeSpending($receiptData);

        return $receiptData;
    }

    /**
     * Basic spending analysis
     */
    protected function analyzeSpending(array $receiptData): array
    {
        $analysis = [];

        // Analyze total amount
        $totalAmount = $receiptData['total_amount'] ?? 0;
        if ($totalAmount > 0) {
            if ($totalAmount > 1000) {
                $analysis['spending_level'] = 'high';
            } elseif ($totalAmount > 200) {
                $analysis['spending_level'] = 'medium';
            } else {
                $analysis['spending_level'] = 'low';
            }
        }

        // Analyze items count
        $itemsCount = count($receiptData['items'] ?? []);
        if ($itemsCount > 10) {
            $analysis['purchase_size'] = 'large';
        } elseif ($itemsCount > 3) {
            $analysis['purchase_size'] = 'medium';
        } else {
            $analysis['purchase_size'] = 'small';
        }

        // Analyze time of purchase (if available)
        if (! empty($receiptData['receipt_date'])) {
            $hour = (int) date('H', strtotime($receiptData['receipt_date']));
            if ($hour >= 6 && $hour < 12) {
                $analysis['time_category'] = 'morning';
            } elseif ($hour >= 12 && $hour < 18) {
                $analysis['time_category'] = 'afternoon';
            } elseif ($hour >= 18 && $hour < 24) {
                $analysis['time_category'] = 'evening';
            } else {
                $analysis['time_category'] = 'night';
            }
        }

        return $analysis;
    }

    /**
     * Generate enhanced description with category information
     */
    public function generateEnhancedDescription(array $data, string $defaultCurrency = 'NOK', ?string $category = null): string
    {
        $parts = [];

        // Add category prefix if available
        if ($category) {
            $parts[] = $category;
        }

        if (! empty($data['merchant']['name'])) {
            $parts[] = 'at '.$data['merchant']['name'];
        }

        if (! empty($data['items'])) {
            $itemCount = count($data['items']);
            $parts[] = $itemCount === 1 ? '1 item' : $itemCount.' items';
        }

        if (! empty($data['totals']['total_amount']) || ! empty($data['totals']['total'])) {
            $total = $data['totals']['total_amount'] ?? $data['totals']['total'];
            $currency = $data['payment']['currency'] ?? $data['currency'] ?? $defaultCurrency;
            $parts[] = number_format($total, 2).' '.$currency;
        }

        return implode(' - ', $parts) ?: 'Receipt';
    }
}
