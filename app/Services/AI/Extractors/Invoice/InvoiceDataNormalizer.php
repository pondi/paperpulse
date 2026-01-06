<?php

namespace App\Services\AI\Extractors\Invoice;

/**
 * Normalizes Gemini's flat invoice structure to EntityFactory format.
 */
class InvoiceDataNormalizer
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
            // Vendor/From info (nested)
            'vendor' => array_filter([
                'name' => $geminiData['vendor_name'] ?? null,
                'address' => $geminiData['vendor_address'] ?? null,
                'vat_number' => $geminiData['vendor_vat_number'] ?? null,
                'email' => $geminiData['vendor_email'] ?? null,
                'phone' => $geminiData['vendor_phone'] ?? null,
            ]),

            // Customer/To info (nested)
            'customer' => array_filter([
                'name' => $geminiData['customer_name'] ?? null,
                'address' => $geminiData['customer_address'] ?? null,
                'vat_number' => $geminiData['customer_vat_number'] ?? null,
                'email' => $geminiData['customer_email'] ?? null,
                'phone' => $geminiData['customer_phone'] ?? null,
            ]),

            // Invoice metadata (nested)
            'invoice_info' => array_filter([
                'invoice_number' => $geminiData['invoice_number'] ?? null,
                'invoice_type' => $geminiData['invoice_type'] ?? 'invoice',
                'invoice_date' => $geminiData['invoice_date'] ?? null,
                'due_date' => $geminiData['due_date'] ?? null,
                'delivery_date' => $geminiData['delivery_date'] ?? null,
                'purchase_order_number' => $geminiData['purchase_order_number'] ?? null,
                'reference_number' => $geminiData['reference_number'] ?? null,
            ]),

            // Line items (already array)
            'line_items' => $geminiData['line_items'] ?? [],

            // Totals (nested)
            'totals' => array_filter([
                'subtotal' => $geminiData['subtotal'] ?? null,
                'tax_amount' => $geminiData['tax_amount'] ?? null,
                'discount_amount' => $geminiData['discount_amount'] ?? null,
                'shipping_amount' => $geminiData['shipping_amount'] ?? null,
                'total_amount' => $geminiData['total_amount'] ?? null,
                'amount_paid' => $geminiData['amount_paid'] ?? null,
                'amount_due' => $geminiData['amount_due'] ?? null,
            ]),

            // Payment (nested)
            'payment' => array_filter([
                'method' => $geminiData['payment_method'] ?? null,
                'status' => $geminiData['payment_status'] ?? 'unpaid',
                'terms' => $geminiData['payment_terms'] ?? null,
                'currency' => $geminiData['currency'] ?? 'NOK',
            ]),

            // Notes
            'notes' => $geminiData['notes'] ?? null,

            // Metadata
            'metadata' => [
                'confidence_score' => $geminiData['confidence_score'] ?? 0.85,
            ],
        ];
    }
}
