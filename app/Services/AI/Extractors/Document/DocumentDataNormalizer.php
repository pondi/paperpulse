<?php

namespace App\Services\AI\Extractors\Document;

/**
 * Normalizes Gemini's flat document structure to EntityFactory format.
 */
class DocumentDataNormalizer
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
            // Document metadata (nested)
            'metadata' => array_filter([
                'title' => $geminiData['document_title'] ?? null,
                'type' => $geminiData['document_type'] ?? null,
                'category' => $geminiData['document_category'] ?? null,
            ]),

            // Author and creation info (nested)
            'creation_info' => array_filter([
                'author' => $geminiData['author'] ?? null,
                'creation_date' => $geminiData['creation_date'] ?? null,
            ]),

            // Content (nested)
            'content' => array_filter([
                'summary' => $geminiData['summary'] ?? null,
                'key_points' => $geminiData['key_points'] ?? [],
            ]),

            // Entities (already array)
            'entities_mentioned' => $geminiData['entities_mentioned'] ?? [],

            // Tags (already array)
            'tags' => $geminiData['tags'] ?? [],

            // Quality indicator
            'quality' => [
                'confidence_score' => $geminiData['confidence_score'] ?? 0.85,
            ],
        ];
    }
}
