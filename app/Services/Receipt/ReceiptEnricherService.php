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
            'vat_number' => $merchantData['vat_number'] ?? null,
        ]);

        $query = Merchant::query();

        if (! empty($merchantData['vat_number'])) {
            $existingMerchant = $query->where('vat_number', $merchantData['vat_number'])->first();
            if ($existingMerchant) {
                Log::debug('[ReceiptEnricher] Found merchant by vat number', [
                    'merchant_id' => $existingMerchant->id,
                    'vat_number' => $merchantData['vat_number'],
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
        
        // Handle address - could be string or array from AI response
        $address = $merchantData['address'] ?? null;
        if (is_array($address)) {
            $merchant->address = implode(', ', array_filter($address));
        } else {
            $merchant->address = $address;
        }
        
        $merchant->vat_number = $merchantData['vat_number'] ?? null;
        $merchant->save();

        Log::info('[ReceiptEnricher] Created new merchant', [
            'merchant_id' => $merchant->id,
            'merchant_name' => $merchant->name,
            'vat_number' => $merchant->vat_number,
        ]);

        return $merchant;
    }

    /**
     * Categorize merchant based on name (deprecated).
     * Category should come from AI output structure. This remains for backward compatibility
     * and will always return null to avoid heuristic misclassification.
     */
    public function categorizeMerchant(string $merchantName): ?string
    {
        Log::notice('[ReceiptEnricher] categorizeMerchant() is deprecated; relying on AI-provided categories', [
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
        // Add merchant/receipt category if available from AI
        $aiCategory = $receiptData['merchant']['category'] ?? $receiptData['receipt_category'] ?? null;
        if (!empty($aiCategory)) {
            $receiptData['suggested_category'] = $aiCategory;
        }

        // Add merchant information
        if ($merchant) {
            $receiptData['merchant_info'] = [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'address' => $merchant->address,
                'vat_number' => $merchant->vat_number,
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
