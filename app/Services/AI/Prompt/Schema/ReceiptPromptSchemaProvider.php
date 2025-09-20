<?php

namespace App\Services\AI\Prompt\Schema;

/**
 * Provides the JSON Schema used for receipt extraction prompts.
 */
class ReceiptPromptSchemaProvider
{
    /** Build the receipt JSON schema. */
    public static function schema(array $options = []): array
    {
        $strictMode = $options['strict_mode'] ?? true;

        return [
            'type' => 'object',
            'properties' => [
                'merchant' => [
                    'type' => 'object',
                    'description' => 'Merchant/store information',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Name of the store or business'],
                        'address' => ['type' => 'string', 'description' => 'Physical address of the store'],
                        'vat_number' => ['type' => 'string', 'description' => 'Norwegian organization/VAT number (9 digits)'],
                        'phone' => ['type' => 'string', 'description' => 'Phone number'],
                        'website' => ['type' => 'string', 'description' => 'Website URL if present'],
                        'email' => ['type' => 'string', 'description' => 'Email address if present'],
                        'category' => ['type' => 'string', 'description' => 'Business category (grocery, restaurant, retail, etc.)'],
                    ],
                    'required' => ['name'],
                    'additionalProperties' => false,
                ],
                'items' => [
                    'type' => 'array',
                    'description' => 'List of purchased items',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Item name or description'],
                            'quantity' => ['type' => 'number', 'description' => 'Quantity purchased'],
                            'unit_price' => ['type' => 'number', 'description' => 'Price per unit'],
                            'total_price' => ['type' => 'number', 'description' => 'Total price for this item'],
                            'discount_amount' => ['type' => 'number', 'description' => 'Discount amount if applicable'],
                            'vat_rate' => ['type' => 'number', 'description' => 'VAT rate (0.25 for 25%, etc.)'],
                            'category' => ['type' => 'string', 'description' => 'Item category'],
                            'sku' => ['type' => 'string', 'description' => 'Product code or SKU if present'],
                            'vendor' => ['type' => 'string', 'description' => 'Product vendor/brand if identifiable (e.g., Philips, Siemens)'],
                            'brand' => ['type' => 'string', 'description' => 'Alias of vendor if provider uses brand terminology'],
                        ],
                        'required' => ['name', 'total_price'],
                        'additionalProperties' => false,
                    ],
                ],
                'totals' => [
                    'type' => 'object',
                    'description' => 'Receipt totals and taxes',
                    'properties' => [
                        'subtotal' => ['type' => 'number', 'description' => 'Subtotal before tax and discounts'],
                        'total_discount' => ['type' => 'number', 'description' => 'Total discount amount'],
                        'tax_amount' => ['type' => 'number', 'description' => 'Total VAT/tax amount'],
                        'total_amount' => ['type' => 'number', 'description' => 'Final total amount paid'],
                        'tip_amount' => ['type' => 'number', 'description' => 'Tip amount if applicable'],
                    ],
                    'required' => ['total_amount'],
                    'additionalProperties' => false,
                ],
                'receipt_info' => [
                    'type' => 'object',
                    'description' => 'Receipt metadata',
                    'properties' => [
                        'date' => ['type' => 'string', 'description' => 'Receipt date in YYYY-MM-DD format'],
                        'time' => ['type' => 'string', 'description' => 'Receipt time in HH:MM format'],
                        'receipt_number' => ['type' => 'string', 'description' => 'Receipt or invoice number'],
                        'transaction_id' => ['type' => 'string', 'description' => 'Transaction ID'],
                        'cashier' => ['type' => 'string', 'description' => 'Cashier name or ID'],
                        'terminal_id' => ['type' => 'string', 'description' => 'Terminal or register ID'],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
                'payment' => [
                    'type' => 'object',
                    'description' => 'Payment information',
                    'properties' => [
                        'method' => ['type' => 'string', 'description' => 'Payment method (cash, card, mobile, etc.)'],
                        'card_type' => ['type' => 'string', 'description' => 'Card type if applicable (Visa, MasterCard, etc.)'],
                        'card_last_four' => ['type' => 'string', 'description' => 'Last four digits of card'],
                        'currency' => ['type' => 'string', 'description' => 'Currency code (NOK, EUR, etc.)'],
                        'change_given' => ['type' => 'number', 'description' => 'Change given if cash payment'],
                        'amount_paid' => ['type' => 'number', 'description' => 'Amount paid by customer'],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
                'loyalty_program' => [
                    'type' => 'object',
                    'description' => 'Loyalty program information',
                    'properties' => [
                        'program_name' => ['type' => 'string', 'description' => 'Name of loyalty program'],
                        'member_id' => ['type' => 'string', 'description' => 'Member ID or number'],
                        'points_earned' => ['type' => 'number', 'description' => 'Points earned from this purchase'],
                        'points_used' => ['type' => 'number', 'description' => 'Points used for discounts'],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Additional metadata',
                    'properties' => [
                        'language' => ['type' => 'string', 'description' => 'Receipt language (no, en, etc.)'],
                        'confidence_score' => ['type' => 'number', 'description' => 'Confidence score for extraction (0-1)'],
                        'receipt_type' => ['type' => 'string', 'description' => 'Type of receipt (sale, return, void, etc.)'],
                        'processing_notes' => ['type' => 'string', 'description' => 'Any notes about processing challenges'],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
                'vendors' => [
                    'type' => 'array',
                    'description' => 'Distinct list of identified product vendors/brands present on this receipt',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['merchant', 'totals', 'receipt_info'],
            'additionalProperties' => $strictMode ? false : true,
        ];
    }
}
