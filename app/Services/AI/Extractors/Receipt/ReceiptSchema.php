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

                    // Description and category
                    'description' => ['type' => 'string', 'description' => 'Brief description of the purchase (e.g., "Grocery shopping at Rema 1000 - purchased milk, bread, and eggs")'],
                    'category' => [
                        'type' => 'string',
                        'description' => 'Purchase category - choose the ONE most appropriate category',
                        'enum' => [
                            // Food & Beverages
                            'Groceries',
                            'Restaurants & Dining',
                            'Coffee & Bakery',
                            // Transportation
                            'Fuel & Gas',
                            'Public Transport',
                            'Parking & Tolls',
                            'Vehicle Maintenance',
                            // Shopping
                            'Clothing & Accessories',
                            'Electronics',
                            'Books & Media',
                            'Sports & Outdoors',
                            'Personal Care & Beauty',
                            // Home & Living
                            'Furniture',
                            'Home Improvement',
                            'Garden & Plants',
                            'Appliances',
                            'Home Decor',
                            // Utilities & Bills
                            'Electricity & Water',
                            'Internet & Phone',
                            'Streaming Services',
                            'Insurance',
                            // Healthcare & Wellness
                            'Pharmacy & Medicine',
                            'Doctor & Medical',
                            'Fitness & Gym',
                            'Dental & Vision',
                            // Entertainment & Leisure
                            'Movies & Events',
                            'Hobbies & Crafts',
                            'Travel & Hotels',
                            'Gaming',
                            // Education & Work
                            'Education & Courses',
                            'Office Supplies',
                            'Professional Services',
                            // Pets & Children
                            'Pet Care & Supplies',
                            'Children & Toys',
                            'Baby Products',
                            // Financial & Legal
                            'Banking & Fees',
                            'Taxes & Legal',
                            'Donations & Charity',
                            // Miscellaneous
                            'Gifts',
                            'Other',
                        ],
                    ],
                    'vendors' => [
                        'type' => 'array',
                        'description' => 'Product brands/vendors mentioned',
                        'items' => ['type' => 'string'],
                    ],

                    // Metadata
                    'confidence_score' => ['type' => 'number', 'description' => 'Extraction confidence (0.0-1.0)'],
                ],
                'required' => ['merchant_name', 'total_amount', 'receipt_date', 'description', 'category'],
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
6. **Description**: Brief description combining merchant, purchase type, and key items (e.g., "Grocery shopping at Rema 1000 - purchased milk, bread, and eggs")
7. **Category**: Choose the ONE most specific category that matches the purchase from these options:

   **Food & Beverages:** Groceries, Restaurants & Dining, Coffee & Bakery
   **Transportation:** Fuel & Gas, Public Transport, Parking & Tolls, Vehicle Maintenance
   **Shopping:** Clothing & Accessories, Electronics, Books & Media, Sports & Outdoors, Personal Care & Beauty
   **Home & Living:** Furniture, Home Improvement, Garden & Plants, Appliances, Home Decor
   **Utilities & Bills:** Electricity & Water, Internet & Phone, Streaming Services, Insurance
   **Healthcare & Wellness:** Pharmacy & Medicine, Doctor & Medical, Fitness & Gym, Dental & Vision
   **Entertainment & Leisure:** Movies & Events, Hobbies & Crafts, Travel & Hotels, Gaming
   **Education & Work:** Education & Courses, Office Supplies, Professional Services
   **Pets & Children:** Pet Care & Supplies, Children & Toys, Baby Products
   **Financial & Legal:** Banking & Fees, Taxes & Legal, Donations & Charity
   **Miscellaneous:** Gifts, Other

8. **Vendors**: Product brands mentioned (e.g., "Apple", "Samsung", "Garmin")

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
  "description": "Home improvement shopping at Clas Ohlson - purchased vacuum bags and potato press",
  "category": "Home Improvement",
  "vendors": ["Clas Ohlson"],
  "confidence_score": 0.95
}
```
PROMPT;
    }
}
