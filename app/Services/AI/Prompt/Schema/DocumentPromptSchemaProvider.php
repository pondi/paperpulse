<?php

namespace App\Services\AI\Prompt\Schema;

/**
 * Provides the JSON Schema used for document analysis prompts.
 */
class DocumentPromptSchemaProvider
{
    /** Build the document JSON schema. */
    public static function schema(array $options = []): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Document title or main heading'],
                'document_type' => [
                    'type' => 'string',
                    'enum' => ['invoice', 'contract', 'report', 'letter', 'memo', 'presentation', 'spreadsheet', 'email', 'legal', 'financial', 'technical', 'other'],
                    'description' => 'Classification of document type',
                ],
                'summary' => ['type' => 'string', 'description' => 'Brief summary of document content'],
                'suggested_category' => ['type' => 'string', 'description' => 'Suggested category for this document, if any'],
                'entities' => [
                    'type' => 'object',
                    'properties' => [
                        'people' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Names of people mentioned'],
                        'organizations' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Organizations mentioned'],
                        'locations' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Locations mentioned'],
                        'dates' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Important dates found'],
                        'amounts' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Financial amounts mentioned'],
                        'phone_numbers' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Phone numbers found'],
                        'emails' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Email addresses found'],
                        'references' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Reference numbers, IDs, etc.'],
                    ],
                ],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Relevant tags for categorization'],
                'language' => ['type' => 'string', 'description' => 'Primary language of document'],
                'key_phrases' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Important phrases or terms'],
                'sentiment' => [
                    'type' => 'object',
                    'properties' => [
                        'overall' => ['type' => 'string', 'enum' => ['positive', 'negative', 'neutral'], 'description' => 'Overall sentiment'],
                        'confidence' => ['type' => 'number', 'description' => 'Confidence in sentiment analysis (0-1)'],
                    ],
                ],
                'urgency' => [
                    'type' => 'object',
                    'properties' => [
                        'level' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical'], 'description' => 'Urgency level of document'],
                        'indicators' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Phrases indicating urgency'],
                    ],
                ],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'page_count' => ['type' => 'number', 'description' => 'Estimated page count'],
                        'word_count' => ['type' => 'number', 'description' => 'Estimated word count'],
                        'confidence_score' => ['type' => 'number', 'description' => 'Overall extraction confidence (0-1)'],
                        'processing_notes' => ['type' => 'string', 'description' => 'Notes about processing challenges or findings'],
                    ],
                ],
                'vendors' => [
                    'type' => 'array',
                    'description' => 'Distinct list of identified product vendors/brands present in this document',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['title', 'document_type'],
            'additionalProperties' => false,
        ];
    }
}
