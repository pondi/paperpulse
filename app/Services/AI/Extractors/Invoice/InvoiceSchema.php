<?php

namespace App\Services\AI\Extractors\Invoice;

/**
 * Simplified invoice schema for Gemini extraction (Pass 2).
 *
 * Flattened to 2-3 levels max to avoid Gemini API 400 errors from deep nesting.
 */
class InvoiceSchema
{
    /**
     * Get simplified invoice schema.
     *
     * @return array Schema configuration with responseSchema key
     */
    public static function get(): array
    {
        return [
            'name' => 'invoice_extraction',
            'responseSchema' => [
                'type' => 'object',
                'properties' => [
                    // Vendor/From info (flattened - no nested object)
                    'vendor_name' => ['type' => 'string', 'description' => 'Vendor/seller business name'],
                    'vendor_address' => ['type' => 'string', 'description' => 'Vendor address'],
                    'vendor_vat_number' => ['type' => 'string', 'description' => 'Vendor VAT/tax number'],
                    'vendor_email' => ['type' => 'string', 'description' => 'Vendor email'],
                    'vendor_phone' => ['type' => 'string', 'description' => 'Vendor phone'],

                    // Customer/To info (flattened)
                    'customer_name' => ['type' => 'string', 'description' => 'Customer/buyer name'],
                    'customer_address' => ['type' => 'string', 'description' => 'Customer address'],
                    'customer_vat_number' => ['type' => 'string', 'description' => 'Customer VAT/tax number'],
                    'customer_email' => ['type' => 'string', 'description' => 'Customer email'],
                    'customer_phone' => ['type' => 'string', 'description' => 'Customer phone'],

                    // Invoice metadata (flattened)
                    'invoice_number' => ['type' => 'string', 'description' => 'Invoice number/identifier'],
                    'invoice_type' => ['type' => 'string', 'description' => 'Invoice type (invoice, proforma, credit_note)'],
                    'invoice_date' => ['type' => 'string', 'description' => 'Invoice date (YYYY-MM-DD)'],
                    'due_date' => ['type' => 'string', 'description' => 'Payment due date (YYYY-MM-DD)'],
                    'delivery_date' => ['type' => 'string', 'description' => 'Delivery date (YYYY-MM-DD)'],
                    'purchase_order_number' => ['type' => 'string', 'description' => 'PO number if present'],
                    'reference_number' => ['type' => 'string', 'description' => 'Reference/tracking number'],

                    // Line items (simplified to 2 levels: array → item properties)
                    'line_items' => [
                        'type' => 'array',
                        'description' => 'Invoice line items',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'line_number' => ['type' => 'number', 'description' => 'Line item number'],
                                'description' => ['type' => 'string', 'description' => 'Item description'],
                                'sku' => ['type' => 'string', 'description' => 'SKU/product code'],
                                'quantity' => ['type' => 'number', 'description' => 'Quantity'],
                                'unit_of_measure' => ['type' => 'string', 'description' => 'Unit (pcs, kg, hours)'],
                                'unit_price' => ['type' => 'number', 'description' => 'Price per unit'],
                                'discount_percent' => ['type' => 'number', 'description' => 'Discount percentage'],
                                'discount_amount' => ['type' => 'number', 'description' => 'Discount amount'],
                                'tax_rate' => ['type' => 'number', 'description' => 'Tax rate (0.25 for 25%)'],
                                'tax_amount' => ['type' => 'number', 'description' => 'Tax amount for item'],
                                'total_amount' => ['type' => 'number', 'description' => 'Total amount for line'],
                                'category' => ['type' => 'string', 'description' => 'Item category'],
                            ],
                            'required' => ['description', 'total_amount'],
                        ],
                    ],

                    // Totals (flattened - no nested object)
                    'subtotal' => ['type' => 'number', 'description' => 'Subtotal before tax'],
                    'tax_amount' => ['type' => 'number', 'description' => 'Total tax amount'],
                    'discount_amount' => ['type' => 'number', 'description' => 'Total discount amount'],
                    'shipping_amount' => ['type' => 'number', 'description' => 'Shipping/delivery cost'],
                    'total_amount' => ['type' => 'number', 'description' => 'Final total amount'],
                    'amount_paid' => ['type' => 'number', 'description' => 'Amount already paid'],
                    'amount_due' => ['type' => 'number', 'description' => 'Amount still due'],

                    // Payment info (flattened)
                    'payment_method' => ['type' => 'string', 'description' => 'Payment method'],
                    'payment_status' => ['type' => 'string', 'description' => 'Payment status (paid, unpaid, partial)'],
                    'payment_terms' => ['type' => 'string', 'description' => 'Payment terms (Net 30, Due on receipt)'],
                    'currency' => ['type' => 'string', 'description' => 'Currency code (NOK, EUR, USD)'],

                    // Notes
                    'notes' => ['type' => 'string', 'description' => 'Additional notes or comments'],

                    // Metadata
                    'confidence_score' => ['type' => 'number', 'description' => 'Extraction confidence (0.0-1.0)'],
                ],
                'required' => ['vendor_name', 'invoice_number', 'invoice_date', 'total_amount'],
            ],
        ];
    }

    /**
     * Get extraction prompt.
     */
    public static function getPrompt(): string
    {
        return <<<'PROMPT'
Extract all invoice information from this document.

**What to extract:**

1. **Vendor details**: Seller name, address, VAT/tax number, contact info
2. **Customer details**: Buyer name, address, VAT/tax number, contact info
3. **Invoice metadata**: Invoice number, type, dates (invoice, due, delivery), PO number, reference
4. **All line items**: Every product/service with description, quantity, price, tax, totals
5. **Totals**: Subtotal, tax, discounts, shipping, final amount, amounts paid/due
6. **Payment**: Method, status, terms, currency
7. **Notes**: Any additional notes or comments

**Important:**
- Extract ALL line items, not just a sample
- Use YYYY-MM-DD format for all dates
- For tax rates, use decimal format (0.25 for 25%)
- Include confidence_score (0.0-1.0) based on data clarity
- For Norwegian invoices: VAT numbers are 9 digits, currency is NOK
- If a field is not present, omit it (don't guess)
- Payment status: "paid", "unpaid", or "partial"
- Invoice type: "invoice", "proforma", "credit_note", etc.

**Example structure:**
```json
{
  "vendor_name": "Acme Corp AS",
  "vendor_address": "Oslo Street 123, Oslo",
  "vendor_vat_number": "987654321",
  "customer_name": "Best Company AS",
  "customer_address": "Bergen Road 456, Bergen",
  "invoice_number": "INV-2025-001",
  "invoice_type": "invoice",
  "invoice_date": "2025-01-15",
  "due_date": "2025-02-14",
  "line_items": [
    {
      "line_number": 1,
      "description": "Consulting Services",
      "quantity": 40,
      "unit_of_measure": "hours",
      "unit_price": 1200.00,
      "tax_rate": 0.25,
      "tax_amount": 12000.00,
      "total_amount": 60000.00
    }
  ],
  "subtotal": 48000.00,
  "tax_amount": 12000.00,
  "total_amount": 60000.00,
  "payment_status": "unpaid",
  "payment_terms": "Net 30",
  "currency": "NOK",
  "confidence_score": 0.95
}
```
PROMPT;
    }
}
