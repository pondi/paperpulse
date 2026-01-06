<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Models\Merchant;

class ReceiptPayloadBuilder
{
    public static function build(
        array $analysis,
        array $data,
        int $userId,
        int $fileId,
        ?int $merchantId,
        ?Merchant $merchant,
        $dateTime,
        array $totals,
        string $currency,
        ?int $categoryId,
        ?string $categoryName,
        ReceiptEnricherContract $enricher,
        string $defaultCurrency,
        ?string $note = null
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
            'note' => $note,
            'receipt_data' => json_encode(array_merge($analysis, ['enriched_data' => $enriched])),
        ];
    }
}
