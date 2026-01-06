<?php

namespace App\Services\AI\Extractors\Document;

/**
 * Simplified document schema for Gemini extraction (generic fallback).
 *
 * Flattened to 2-3 levels max to avoid Gemini API 400 errors from deep nesting.
 * Designed as a generic fallback for documents that don't match receipt/invoice/contract types.
 */
class DocumentSchema
{
    /**
     * Get simplified document schema.
     *
     * @return array Schema configuration with responseSchema key
     */
    public static function get(): array
    {
        return [
            'name' => 'document_extraction',
            'responseSchema' => [
                'type' => 'object',
                'properties' => [
                    // Document metadata (flattened)
                    'document_title' => ['type' => 'string', 'description' => 'Document title or heading'],
                    'document_type' => ['type' => 'string', 'description' => 'Type of document (letter, report, memo, article, etc.)'],
                    'document_category' => ['type' => 'string', 'description' => 'Document category (business, personal, legal, technical, etc.)'],

                    // Author and dates (flattened)
                    'author' => ['type' => 'string', 'description' => 'Document author name'],
                    'creation_date' => ['type' => 'string', 'description' => 'Creation date (YYYY-MM-DD)'],

                    // Content summary and structure
                    'summary' => ['type' => 'string', 'description' => '2-3 sentence summary of document content'],
                    'key_points' => [
                        'type' => 'array',
                        'description' => 'List of main points or topics covered',
                        'items' => ['type' => 'string'],
                    ],

                    // Entities mentioned (simplified to 2 levels: array → properties)
                    'entities_mentioned' => [
                        'type' => 'array',
                        'description' => 'Named entities mentioned in document (people, organizations, locations)',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'entity_name' => ['type' => 'string', 'description' => 'Name of entity'],
                                'entity_type' => ['type' => 'string', 'description' => 'Type (person, organization, location, product)'],
                            ],
                            'required' => ['entity_name', 'entity_type'],
                        ],
                    ],

                    // Tags and classification
                    'tags' => [
                        'type' => 'array',
                        'description' => 'Relevant tags or keywords for the document',
                        'items' => ['type' => 'string'],
                    ],

                    // Quality indicator
                    'confidence_score' => ['type' => 'number', 'description' => 'Extraction confidence (0.0-1.0)'],
                ],
                'required' => ['document_title'],
            ],
        ];
    }

    /**
     * Get extraction prompt.
     */
    public static function getPrompt(): string
    {
        return <<<'PROMPT'
Extract all document information from this text or image.

**What to extract:**

1. **Document metadata**: Title, type (letter, report, memo, article), category (business, personal, legal, technical)
2. **Author and dates**: Author name, creation date (YYYY-MM-DD format)
3. **Content summary**: 2-3 sentence overview of what the document contains
4. **Key points**: Main topics, ideas, or findings covered (as bullet points)
5. **Entities mentioned**: All named entities such as:
   - People (names, titles)
   - Organizations (companies, institutions)
   - Locations (cities, countries)
   - Products or brands
6. **Tags**: Relevant keywords or topics that classify the document
7. **Confidence**: Your confidence in the extraction (0.0-1.0) based on clarity and completeness

**Important:**
- Extract document_title (required) - this is the main subject/title
- Use YYYY-MM-DD format for dates
- Include all named entities with their type
- Be comprehensive with key_points - extract all main topics
- If a field is not clearly present, omit it (don't guess)
- confidence_score should reflect data clarity: 0.95+ if very clear, 0.7-0.85 if somewhat clear, below 0.7 if unclear

**Example structure:**
```json
{
  "document_title": "Quarterly Business Report Q3 2024",
  "document_type": "report",
  "document_category": "business",
  "author": "John Smith",
  "creation_date": "2024-10-15",
  "summary": "This quarterly report covers company performance for Q3 2024, showing revenue growth of 15% and expansion into new markets. The report highlights key achievements, challenges, and strategic recommendations for Q4.",
  "key_points": [
    "Revenue increased to $2.5M, up 15% from Q2",
    "Successfully launched product in EU market",
    "Headcount grew from 45 to 52 employees",
    "Customer retention rate improved to 94%",
    "Recommended focus on Asian expansion for Q4"
  ],
  "entities_mentioned": [
    {"entity_name": "John Smith", "entity_type": "person"},
    {"entity_name": "Acme Corp", "entity_type": "organization"},
    {"entity_name": "EU", "entity_type": "location"}
  ],
  "tags": ["quarterly", "business", "financial", "strategy"],
  "confidence_score": 0.92
}
```
PROMPT;
    }
}
