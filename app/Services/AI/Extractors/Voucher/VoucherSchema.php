<?php

namespace App\Services\AI\Extractors\Voucher;

/**
 * Simplified voucher schema for Gemini extraction.
 *
 * Flattened to 2-3 levels max to avoid Gemini API 400 errors from deep nesting.
 */
class VoucherSchema
{
    /**
     * Get simplified voucher schema.
     *
     * @return array Schema configuration with responseSchema key
     */
    public static function get(): array
    {
        return [
            'name' => 'voucher_extraction',
            'responseSchema' => [
                'type' => 'object',
                'properties' => [
                    // Issuer information (flattened - no nested object)
                    'issuer_name' => ['type' => 'string', 'description' => 'Name of the organization issuing the voucher'],
                    'issuer_contact' => ['type' => 'string', 'description' => 'Contact information (email, phone, website)'],

                    // Voucher details (flattened)
                    'voucher_code' => ['type' => 'string', 'description' => 'Unique voucher code or serial number'],
                    'voucher_type' => ['type' => 'string', 'description' => 'Type of voucher (discount, gift card, store credit, etc.)'],

                    // Dates (flattened)
                    'issue_date' => ['type' => 'string', 'description' => 'Issue date (YYYY-MM-DD)'],
                    'expiry_date' => ['type' => 'string', 'description' => 'Expiry date (YYYY-MM-DD)'],

                    // Value information (flattened)
                    'value_amount' => ['type' => 'number', 'description' => 'Face value of the voucher'],
                    'currency' => ['type' => 'string', 'description' => 'Currency code (NOK, EUR, USD)'],

                    // Redemption details (flattened)
                    'redemption_instructions' => ['type' => 'string', 'description' => 'How to redeem the voucher'],
                    'terms_conditions' => ['type' => 'string', 'description' => 'Terms and conditions'],

                    // Metadata
                    'confidence_score' => ['type' => 'number', 'description' => 'Extraction confidence (0.0-1.0)'],
                ],
                'required' => ['issuer_name', 'voucher_code', 'expiry_date', 'value_amount'],
            ],
        ];
    }

    /**
     * Get extraction prompt.
     */
    public static function getPrompt(): string
    {
        return <<<'PROMPT'
Extract all voucher information from this document.

**What to extract:**

1. **Issuer details**: Organization name, contact information (email, phone, website)
2. **Voucher details**: Unique code/serial number, voucher type (discount, gift card, store credit, etc.)
3. **Dates**: Issue date (YYYY-MM-DD), expiry date (YYYY-MM-DD)
4. **Value**: Face value amount, currency code
5. **Redemption**: How to redeem, terms and conditions
6. **Confidence**: Your confidence in the extraction (0.0-1.0)

**Important:**
- Extract the voucher code exactly as written
- Use YYYY-MM-DD format for all dates
- Include confidence_score based on document clarity and completeness
- If a field is not present, omit it (don't guess)
- Redemption instructions should include any special conditions or limitations

**Example structure:**
```json
{
  "issuer_name": "Spotify",
  "issuer_contact": "support@spotify.com",
  "voucher_code": "SPOTIFY-GIFT-ABC123DEF456",
  "voucher_type": "gift card",
  "issue_date": "2025-01-01",
  "expiry_date": "2026-01-01",
  "value_amount": 100,
  "currency": "NOK",
  "redemption_instructions": "Visit spotify.com/redeem and enter the code",
  "terms_conditions": "Valid for 12 months. Cannot be transferred or refunded.",
  "confidence_score": 0.95
}
```
PROMPT;
    }
}
