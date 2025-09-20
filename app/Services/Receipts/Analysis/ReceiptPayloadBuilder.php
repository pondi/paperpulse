<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptEnricherContract;

class ReceiptPayloadBuilder
{
    public static function build(
        array $analysis,
        array $data,
        int $userId,
        int $fileId,
        ?int $merchantId,
        ?\App\Models\Merchant $merchant,
        $dateTime,
        array $totals,
        string $currency,
        ?int $categoryId,
        ?string $categoryName,
        ReceiptEnricherContract $enricher,
        string $defaultCurrency
    ): array {
        $enriched = $enricher->enrichReceiptData($data, $merchant);

        return [
            'user_id' => $userId,
            'file_id' => $fileId,
            'merchant_id' => $merchantId,
            'receipt_date' => $dateTime,
            'total_amount' => $totals['total_amount'],
            'tax_amount' => $totals['tax_amount'],
            'currency' => $currency,
            'category_id' => $categoryId,
            'receipt_category' => $categoryName,
            'receipt_description' => $enricher->generateEnhancedDescription($data, $defaultCurrency, $categoryName),
            'receipt_data' => json_encode(array_merge($analysis, ['enriched_data' => $enriched])),
        ];
    }
}
