<?php

namespace App\Services\AI\Extractors\Warranty;

/**
 * Normalizes Gemini's flat warranty structure to EntityFactory format.
 */
class WarrantyDataNormalizer
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
            // Provider info (nested)
            'provider' => array_filter([
                'name' => $geminiData['provider_name'] ?? null,
                'contact' => $geminiData['provider_contact'] ?? null,
            ]),

            // Product info (nested)
            'product' => array_filter([
                'name' => $geminiData['product_name'] ?? null,
                'model' => $geminiData['product_model'] ?? null,
                'serial_number' => $geminiData['serial_number'] ?? null,
            ]),

            // Warranty dates (nested)
            'dates' => array_filter([
                'purchase_date' => $geminiData['purchase_date'] ?? null,
                'warranty_start_date' => $geminiData['warranty_start_date'] ?? null,
                'warranty_end_date' => $geminiData['warranty_end_date'] ?? null,
            ]),

            // Coverage info (nested)
            'coverage' => array_filter([
                'type' => $geminiData['coverage_type'] ?? null,
                'details' => $geminiData['coverage_details'] ?? null,
                'terms_conditions' => $geminiData['terms_conditions'] ?? null,
            ]),

            // Metadata
            'metadata' => [
                'confidence_score' => $geminiData['confidence_score'] ?? 0.85,
            ],
        ];
    }
}
