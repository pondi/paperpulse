<?php

namespace App\Contracts\Services;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\User;

interface ReceiptEnricherContract
{
    /**
     * Find or create merchant based on extracted data
     */
    public function findOrCreateMerchant(array $merchantData): ?Merchant;

    /**
     * Categorize merchant based on name
     */
    public function categorizeMerchant(string $merchantName): ?string;

    /**
     * Find user's category by name
     */
    public function findUserCategory(User $user, string $categoryName): ?Category;

    /**
     * Enrich receipt data with additional information
     */
    public function enrichReceiptData(array $receiptData, ?Merchant $merchant = null): array;

    /**
     * Generate enhanced description with category information
     */
    public function generateEnhancedDescription(array $data, string $defaultCurrency = 'NOK', ?string $category = null): string;
}
