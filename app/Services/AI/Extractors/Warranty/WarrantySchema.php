<?php

namespace App\Services\AI\Extractors\Warranty;

/**
 * Simplified warranty schema for Gemini extraction.
 *
 * Flattened to 2-3 levels max to avoid Gemini API 400 errors from deep nesting.
 */
class WarrantySchema
{
    /**
     * Get simplified warranty schema.
     *
     * @return array Schema configuration with responseSchema key
     */
    public static function get(): array
    {
        return [
            'name' => 'warranty_extraction',
            'responseSchema' => [
                'type' => 'object',
                'properties' => [
                    // Provider info (flattened)
                    'provider_name' => ['type' => 'string', 'description' => 'Warranty provider company name'],
                    'provider_contact' => ['type' => 'string', 'description' => 'Provider contact details (phone, email, website)'],

                    // Product info (flattened)
                    'product_name' => ['type' => 'string', 'description' => 'Product name'],
                    'product_model' => ['type' => 'string', 'description' => 'Product model number'],
                    'serial_number' => ['type' => 'string', 'description' => 'Product serial number'],

                    // Dates (flattened)
                    'purchase_date' => ['type' => 'string', 'description' => 'Purchase date (YYYY-MM-DD)'],
                    'warranty_start_date' => ['type' => 'string', 'description' => 'Warranty start date (YYYY-MM-DD)'],
                    'warranty_end_date' => ['type' => 'string', 'description' => 'Warranty end date (YYYY-MM-DD)'],

                    // Coverage info (flattened)
                    'coverage_type' => ['type' => 'string', 'description' => 'Type of coverage (e.g., manufacturer, extended, accidental)'],
                    'coverage_details' => ['type' => 'string', 'description' => 'Detailed description of what is covered'],
                    'terms_conditions' => ['type' => 'string', 'description' => 'Key warranty terms and conditions'],

                    // Metadata
                    'confidence_score' => ['type' => 'number', 'description' => 'Extraction confidence (0.0-1.0)'],
                ],
                'required' => ['provider_name', 'product_name', 'warranty_end_date'],
            ],
        ];
    }

    /**
     * Get extraction prompt.
     */
    public static function getPrompt(): string
    {
        return <<<'PROMPT'
Extract all warranty information from this document.

**What to extract:**

1. **Provider details**: Company name offering the warranty, contact information (phone, email, website)
2. **Product information**: Product name, model number, serial number
3. **Warranty dates**: Purchase date (YYYY-MM-DD), warranty start date (YYYY-MM-DD), warranty end date (YYYY-MM-DD)
4. **Coverage details**: Type of coverage (manufacturer, extended, accidental damage), what is covered, key terms and conditions
5. **Confidence score**: Based on data clarity and completeness

**Important:**
- Use YYYY-MM-DD format for all dates
- Extract ONLY what is explicitly stated in the document
- If a field is not present, omit it (don't guess)
- Include confidence_score (0.0-1.0)
- Warranty end date is critical - extract it carefully

**Example structure:**
```json
{
  "provider_name": "AppleCare",
  "provider_contact": "+47 95 00 50 00",
  "product_name": "MacBook Pro 16-inch",
  "product_model": "MNWA3D/A",
  "serial_number": "C02YD05NQMG0",
  "purchase_date": "2024-01-15",
  "warranty_start_date": "2024-01-15",
  "warranty_end_date": "2026-01-15",
  "coverage_type": "AppleCare+",
  "coverage_details": "Hardware repairs, accidental damage protection, battery service",
  "terms_conditions": "Covers hardware defects and accidental damage. Excludes cosmetic damage.",
  "confidence_score": 0.92
}
```
PROMPT;
    }
}
