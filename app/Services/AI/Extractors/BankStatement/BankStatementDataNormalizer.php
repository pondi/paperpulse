<?php

namespace App\Services\AI\Extractors\BankStatement;

/**
 * Normalizes Gemini's flat bank statement structure to EntityFactory format.
 */
class BankStatementDataNormalizer
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
            // Bank info (nested)
            'bank' => array_filter([
                'name' => $geminiData['bank_name'] ?? null,
                'account_holder' => $geminiData['account_holder'] ?? null,
                'account_number' => $geminiData['account_number'] ?? null,
            ]),

            // Statement period (nested)
            'statement_period' => array_filter([
                'start_date' => $geminiData['statement_period_start'] ?? null,
                'end_date' => $geminiData['statement_period_end'] ?? null,
            ]),

            // Balances (nested)
            'balances' => array_filter([
                'opening_balance' => $geminiData['opening_balance'] ?? null,
                'closing_balance' => $geminiData['closing_balance'] ?? null,
                'currency' => $geminiData['currency'] ?? 'NOK',
            ]),

            // Transactions (already array)
            'transactions' => $geminiData['transactions'] ?? [],

            // Metadata
            'metadata' => [
                'confidence_score' => $geminiData['confidence_score'] ?? 0.85,
            ],
        ];
    }
}
