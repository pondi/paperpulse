<?php

namespace App\Services\AI\Extractors\Receipt;

/**
 * Simplified receipt schema for Gemini extraction (Pass 2).
 *
 * Flattened to 2-3 levels max to avoid Gemini API 400 errors from deep nesting.
 */
class ReceiptSchema
{
    /**
     * Get simplified receipt schema.
     *
     * @return array Schema configuration with responseSchema key
     */
    public static function get(): array
    {
        return [
            'name' => 'receipt_extraction',
            'responseSchema' => [
                'type' => 'object',
                'properties' => [
                    // Merchant info (flattened - no nested object)
                    'merchant_name' => ['type' => 'string', 'description' => 'Store/business name'],
                    'merchant_address' => ['type' => 'string', 'description' => 'Store address'],
                    'merchant_vat_number' => ['type' => 'string', 'description' => 'VAT/organization number'],
                    'merchant_phone' => ['type' => 'string', 'description' => 'Phone number'],
                    'merchant_category' => ['type' => 'string', 'description' => 'Business category (grocery, restaurant, retail, etc.)'],

                    // Receipt metadata (flattened)
                    'receipt_date' => ['type' => 'string', 'description' => 'Receipt date (YYYY-MM-DD)'],
                    'receipt_time' => ['type' => 'string', 'description' => 'Receipt time (HH:MM)'],
                    'receipt_number' => ['type' => 'string', 'description' => 'Receipt or invoice number'],

                    // Line items (simplified to 2 levels: array → item properties)
                    'items' => [
                        'type' => 'array',
                        'description' => 'List of purchased items',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => 'Item name'],
                                'quantity' => ['type' => 'number', 'description' => 'Quantity'],
                                'unit_price' => ['type' => 'number', 'description' => 'Price per unit'],
                                'total_price' => ['type' => 'number', 'description' => 'Total price for item'],
                                'vat_rate' => ['type' => 'number', 'description' => 'VAT rate (0.25 for 25%)'],
                            ],
                            'required' => ['name', 'total_price'],
                        ],
                    ],

                    // Totals (flattened - no nested object)
                    'subtotal' => ['type' => 'number', 'description' => 'Subtotal before tax'],
                    'tax_amount' => ['type' => 'number', 'description' => 'Total tax/VAT amount'],
                    'total_amount' => ['type' => 'number', 'description' => 'Final total amount paid'],
                    'total_discount' => ['type' => 'number', 'description' => 'Total discount amount'],

                    // Payment (flattened)
                    'payment_method' => ['type' => 'string', 'description' => 'Payment method (cash, card, mobile)'],
                    'card_type' => ['type' => 'string', 'description' => 'Card type if applicable (Visa, Mastercard)'],
                    'currency' => ['type' => 'string', 'description' => 'Currency code (NOK, EUR, USD)'],

                    // Summary and vendors
                    'summary' => ['type' => 'string', 'description' => '1-2 sentence summary of purchase'],
                    'vendors' => [
                        'type' => 'array',
                        'description' => 'Product brands/vendors mentioned',
                        'items' => ['type' => 'string'],
                    ],

                    // Metadata
                    'confidence_score' => ['type' => 'number', 'description' => 'Extraction confidence (0.0-1.0)'],
                ],
                'required' => ['merchant_name', 'total_amount', 'receipt_date'],
            ],
        ];
    }

    /**
     * Get extraction prompt.
     */
    public static function getPrompt(): string
    {
        return <<<'PROMPT'
Extract all receipt information from this document.

**What to extract:**

1. **Merchant details**: Store name, address, VAT number, phone, category
2. **Receipt metadata**: Date (YYYY-MM-DD), time (HH:MM), receipt number
3. **All line items**: Every product/service purchased with name, quantity, price
4. **Totals**: Subtotal, tax, discounts, final amount
5. **Payment**: Method (cash/card/mobile), card type, currency
6. **Summary**: 1-2 sentence description of what was purchased
7. **Vendors**: Product brands mentioned (e.g., "Apple", "Samsung", "Garmin")

**Important:**
- Extract ALL items, not just a sample
- Use YYYY-MM-DD format for dates
- Use HH:MM format for time (24-hour)
- Include confidence_score (0.0-1.0) based on data clarity
- For Norwegian receipts: VAT numbers are 9 digits, currency is NOK
- If a field is not present, omit it (don't guess)

**Example structure:**
```json
{
  "merchant_name": "Clas Ohlson",
  "merchant_address": "CC Vest, Oslo",
  "merchant_vat_number": "937402198",
  "receipt_date": "2025-04-04",
  "receipt_time": "14:44",
  "items": [
    {"name": "VAKUUMPOSE 22X30C", "quantity": 3, "unit_price": 179.90, "total_price": 539.70}
  ],
  "total_amount": 539.70,
  "tax_amount": 107.94,
  "payment_method": "card",
  "currency": "NOK",
  "summary": "Purchase of vacuum bags and potato press.",
  "vendors": ["Clas Ohlson"],
  "confidence_score": 0.95
}
```
PROMPT;
    }
}
