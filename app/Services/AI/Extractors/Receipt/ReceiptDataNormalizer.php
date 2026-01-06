<?php

namespace App\Services\AI\Extractors\Receipt;

/**
 * Normalizes Gemini's flat receipt structure to EntityFactory format.
 */
class ReceiptDataNormalizer
{
    /**
     * Normalize flat Gemini data to EntityFactory format.
     *
     * Converts flat structure to nested structure expected by EntityFactory.
     *
     * @param  array  $geminiData  Flat data from Gemini
     * @return array Nested data for EntityFactory
     */
    public function normalize(array $geminiData): array
    {
        return [
            // Merchant info (nested)
            'merchant' => array_filter([
                'name' => $geminiData['merchant_name'] ?? null,
                'address' => $geminiData['merchant_address'] ?? null,
                'vat_number' => $geminiData['merchant_vat_number'] ?? null,
                'phone' => $geminiData['merchant_phone'] ?? null,
                'category' => $geminiData['merchant_category'] ?? null,
            ]),

            // Receipt info (nested)
            'receipt_info' => array_filter([
                'date' => $geminiData['receipt_date'] ?? null,
                'time' => $geminiData['receipt_time'] ?? null,
                'receipt_number' => $geminiData['receipt_number'] ?? null,
            ]),

            // Items (already array)
            'items' => $geminiData['items'] ?? [],

            // Totals (nested)
            'totals' => array_filter([
                'subtotal' => $geminiData['subtotal'] ?? null,
                'tax_amount' => $geminiData['tax_amount'] ?? null,
                'total_amount' => $geminiData['total_amount'] ?? null,
                'total_discount' => $geminiData['total_discount'] ?? null,
            ]),

            // Payment (nested)
            'payment' => array_filter([
                'method' => $geminiData['payment_method'] ?? null,
                'card_type' => $geminiData['card_type'] ?? null,
                'currency' => $geminiData['currency'] ?? 'NOK',
            ]),

            // Metadata
            'receipt_description' => $geminiData['description'] ?? null,
            'receipt_category' => $geminiData['category'] ?? null,
            'vendors' => $geminiData['vendors'] ?? [],
            'metadata' => [
                'confidence_score' => $geminiData['confidence_score'] ?? 0.85,
            ],
        ];
    }
}
