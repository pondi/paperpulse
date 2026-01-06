<?php

namespace App\Services\AI\Extractors\Voucher;

/**
 * Normalizes Gemini's flat voucher structure to EntityFactory format.
 */
class VoucherDataNormalizer
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
            // Issuer info (nested)
            'issuer' => array_filter([
                'name' => $geminiData['issuer_name'] ?? null,
                'contact' => $geminiData['issuer_contact'] ?? null,
            ]),

            // Voucher details (nested)
            'voucher' => array_filter([
                'code' => $geminiData['voucher_code'] ?? null,
                'type' => $geminiData['voucher_type'] ?? null,
            ]),

            // Dates (nested)
            'dates' => array_filter([
                'issue_date' => $geminiData['issue_date'] ?? null,
                'expiry_date' => $geminiData['expiry_date'] ?? null,
            ]),

            // Value (nested)
            'value' => array_filter([
                'amount' => $geminiData['value_amount'] ?? null,
                'currency' => $geminiData['currency'] ?? 'NOK',
            ]),

            // Redemption (nested)
            'redemption' => array_filter([
                'instructions' => $geminiData['redemption_instructions'] ?? null,
                'terms_conditions' => $geminiData['terms_conditions'] ?? null,
            ]),

            // Metadata
            'metadata' => [
                'confidence_score' => $geminiData['confidence_score'] ?? 0.85,
            ],
        ];
    }
}
