<?php

namespace App\Services\AI\Extractors\Contract;

/**
 * Normalizes Gemini's flat contract structure to EntityFactory format.
 */
class ContractDataNormalizer
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
            // Contract identification
            'contract_number' => $geminiData['contract_number'] ?? null,
            'contract_title' => $geminiData['contract_title'] ?? null,
            'contract_type' => $geminiData['contract_type'] ?? null,

            // Parties (already array)
            'parties' => $geminiData['parties'] ?? [],

            // Dates
            'dates' => array_filter([
                'effective_date' => $geminiData['effective_date'] ?? null,
                'expiry_date' => $geminiData['expiry_date'] ?? null,
                'signature_date' => $geminiData['signature_date'] ?? null,
            ]),

            // Terms
            'terms' => array_filter([
                'duration' => $geminiData['duration'] ?? null,
                'renewal_terms' => $geminiData['renewal_terms'] ?? null,
            ]),

            // Financial terms
            'financial' => array_filter([
                'contract_value' => $geminiData['contract_value'] ?? null,
                'currency' => $geminiData['currency'] ?? null,
                'payment_schedule' => $geminiData['payment_schedule'] ?? [],
            ]),

            // Legal terms
            'legal' => array_filter([
                'governing_law' => $geminiData['governing_law'] ?? null,
                'jurisdiction' => $geminiData['jurisdiction'] ?? null,
                'termination_conditions' => $geminiData['termination_conditions'] ?? null,
            ]),

            // Obligations and summary
            'key_obligations' => $geminiData['key_obligations'] ?? [],
            'summary' => $geminiData['summary'] ?? null,
            'status' => $geminiData['status'] ?? null,

            // Metadata
            'metadata' => [
                'confidence_score' => $geminiData['confidence_score'] ?? 0.85,
            ],
        ];
    }
}
