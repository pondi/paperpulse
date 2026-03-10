<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Exceptions\GeminiApiException;
use Illuminate\Support\Facades\Log;

/**
 * Handles parsing, cleaning, and repairing JSON responses from the Gemini API.
 */
class GeminiResponseParser
{
    /**
     * Extract text response from Gemini API payload.
     */
    public function extractTextResponse(array $responseBody): string
    {
        $parts = $responseBody['candidates'][0]['content']['parts'] ?? [];
        $texts = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $texts[] = $part['text'];
            }
        }

        return trim(implode("\n", $texts));
    }

    /**
     * Parse JSON response content from Gemini.
     *
     * @return array<string, mixed>
     */
    public function parseJsonResponse(string $text): array
    {
        // Try parsing as-is first
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->cleanNulls($decoded);
        }

        // Try extracting JSON snippet
        $jsonSnippet = $this->extractJsonSnippet($text);
        $decoded = json_decode($jsonSnippet, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->cleanNulls($decoded);
        }

        // Try cleaning and repairing the text
        $cleanedText = $this->cleanJsonText($text);
        $decoded = json_decode($cleanedText, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            Log::info('[GeminiResponseParser] JSON cleaned successfully');

            return $this->cleanNulls($decoded);
        }

        // Try repairing known issues
        $repairedText = $this->repairJson($cleanedText);
        $decoded = json_decode($repairedText, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            Log::info('[GeminiResponseParser] JSON repaired successfully');

            return $this->cleanNulls($decoded);
        }

        // Try repairing the snippet
        $repairedSnippet = $this->repairJson($jsonSnippet);
        $decoded = json_decode($repairedSnippet, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            Log::info('[GeminiResponseParser] JSON snippet repaired successfully');

            return $this->cleanNulls($decoded);
        }

        // All parsing attempts failed
        throw new GeminiApiException(
            'Gemini response is not valid JSON.',
            GeminiApiException::CODE_RESPONSE_INVALID,
            false,
            [
                'error' => json_last_error_msg(),
                'response_length' => strlen($text),
                'response_preview' => substr($text, 0, 500),
            ]
        );
    }

    /**
     * Clean JSON text from common LLM formatting issues.
     */
    protected function cleanJsonText(string $text): string
    {
        // Remove trailing commas before closing brackets/braces
        $text = preg_replace('/,(\s*[\]}])/m', '$1', $text);

        // Fix newlines after colons (Gemini 2.0 Flash bug: "key":\n      value)
        $text = preg_replace('/:[\s\n]+/', ': ', $text);

        // Normalize whitespace in arrays
        $text = preg_replace('/\[\s+/', '[', $text);
        $text = preg_replace('/\s+\]/', ']', $text);

        // Fix arrays with excessive nulls
        $text = preg_replace('/\[\s*null\s*(?:,\s*null\s*)*\]/m', '[]', $text);

        return $text;
    }

    /**
     * Recursively remove null-only arrays and clean null values.
     */
    protected function cleanNulls(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $nonNulls = array_filter($value, fn ($v) => $v !== null);
                if (empty($nonNulls) && ! empty($value)) {
                    $data[$key] = [];
                } else {
                    $data[$key] = $this->cleanNulls($value);
                }
            }
        }

        return $data;
    }

    /**
     * Repair known JSON malformations from Gemini.
     */
    protected function repairJson(string $text): string
    {
        $knownTypes = [
            'receipt', 'voucher', 'warranty', 'return_policy',
            'invoice', 'invoice_line_items', 'contract',
            'bank_statement', 'bank_transactions', 'document',
        ];

        $typesPattern = implode('|', $knownTypes);
        $pattern = '/\}\s*,\s*"type"\s*:\s*"('.$typesPattern.')"/';

        return preg_replace($pattern, '}}, {"type": "$1"', $text);
    }

    /**
     * Extract JSON snippet from a response string.
     */
    protected function extractJsonSnippet(string $text): string
    {
        if (preg_match('/```json\\s*(.*?)\\s*```/s', $text, $matches)) {
            return trim($matches[1]);
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }

    /**
     * Normalize entities from Gemini response to expected format.
     *
     * Gemini 2.0 Flash sometimes ignores the responseSchema and returns flat structures.
     */
    public function normalizeEntities(array $entities, array $schema): array
    {
        $normalized = [];
        $primaryType = $schema['primary_entity'] ?? null;

        foreach ($entities as $index => $entity) {
            if (! is_array($entity)) {
                Log::warning('[GeminiResponseParser] Skipping non-array entity', ['index' => $index]);

                continue;
            }

            // Check if already in correct format
            if (isset($entity['type']) && isset($entity['data'])) {
                $normalized[] = $entity;

                continue;
            }

            // Entity is in wrong format - need to normalize
            $type = $this->detectEntityType($entity, $primaryType, $index);
            $confidenceScore = $entity['confidence_score'] ?? 0.85;

            // Remove meta fields that shouldn't be in data
            $metaFields = ['type', 'confidence_score', 'receipt', 'merchant', 'document'];
            $data = array_diff_key($entity, array_flip($metaFields));

            // Restructure based on type
            $data = $this->restructureEntityData($data, $type);

            $normalized[] = [
                'type' => $type,
                'confidence_score' => $confidenceScore,
                'data' => $data,
            ];

            Log::info('[GeminiResponseParser] Normalized entity', [
                'original_keys' => array_keys($entity),
                'normalized_type' => $type,
                'data_keys' => array_keys($data),
            ]);
        }

        return $normalized;
    }

    /**
     * Detect the entity type from a flat entity structure.
     */
    protected function detectEntityType(array $entity, ?string $defaultType, int $index): string
    {
        if (isset($entity['type'])) {
            return strtolower($entity['type']);
        }

        if (isset($entity['merchant']) || isset($entity['date']) || isset($entity['total']) || isset($entity['receipt_number']) || isset($entity['purchase_items'])) {
            return 'receipt';
        }

        if (isset($entity['code']) || isset($entity['voucher_type']) || isset($entity['expiry_date'])) {
            return 'voucher';
        }

        if (isset($entity['product_name']) || isset($entity['warranty_end_date']) || isset($entity['serial_number'])) {
            return 'warranty';
        }

        if (isset($entity['invoice_number']) || isset($entity['from_name']) || isset($entity['to_name'])) {
            return 'invoice';
        }

        if (isset($entity['contract_number']) || isset($entity['contract_title']) || isset($entity['parties'])) {
            return 'contract';
        }

        if (isset($entity['account_number']) || isset($entity['iban']) || isset($entity['bank_name'])) {
            return 'bank_statement';
        }

        if ($index === 0 && $defaultType) {
            return $defaultType;
        }

        return 'document';
    }

    /**
     * Restructure flat entity data into nested structure based on type.
     */
    protected function restructureEntityData(array $data, string $type): array
    {
        if ($type === 'receipt') {
            return $this->restructureReceiptData($data);
        }

        return $data;
    }

    /**
     * Restructure flat receipt data into expected nested structure.
     */
    protected function restructureReceiptData(array $flat): array
    {
        $structured = [];

        // Map merchant fields
        $merchantFields = ['name', 'address', 'vat_number', 'phone', 'website', 'email', 'category', 'contact_details'];
        $merchant = [];
        foreach ($merchantFields as $field) {
            if (isset($flat[$field])) {
                $merchant[$field] = $flat[$field];
                unset($flat[$field]);
            }
        }
        if (! empty($merchant)) {
            $structured['merchant'] = $merchant;
        }

        // Map totals
        $totalsFields = ['subtotal', 'total', 'total_amount', 'tax', 'tax_amount', 'discount', 'total_discount', 'tip_amount'];
        $totals = [];
        foreach ($totalsFields as $field) {
            if (isset($flat[$field])) {
                $normalizedField = match ($field) {
                    'total' => 'total_amount',
                    'tax' => 'tax_amount',
                    'discount' => 'total_discount',
                    default => $field
                };
                $totals[$normalizedField] = $flat[$field];
                unset($flat[$field]);
            }
        }
        if (! empty($totals)) {
            $structured['totals'] = $totals;
        }

        // Map receipt_info
        $receiptInfoFields = ['date', 'time', 'receipt_number', 'transaction_id', 'cashier', 'terminal_id'];
        $receiptInfo = [];
        foreach ($receiptInfoFields as $field) {
            if (isset($flat[$field])) {
                $receiptInfo[$field] = $flat[$field];
                unset($flat[$field]);
            }
        }
        if (! empty($receiptInfo)) {
            $structured['receipt_info'] = $receiptInfo;
        }

        // Map payment
        $paymentFields = ['payment_method', 'card_type', 'card_last_four', 'currency', 'change_given', 'amount_paid'];
        $payment = [];
        foreach ($paymentFields as $field) {
            if (isset($flat[$field])) {
                $normalizedField = str_replace('payment_', '', $field);
                $payment[$normalizedField] = $flat[$field];
                unset($flat[$field]);
            }
        }
        if (! empty($payment)) {
            $structured['payment'] = $payment;
        }

        // Map items
        if (isset($flat['items'])) {
            $structured['items'] = $flat['items'];
            unset($flat['items']);
        } elseif (isset($flat['purchase_items'])) {
            $items = [];
            foreach ($flat['purchase_items'] as $itemName) {
                $items[] = [
                    'name' => $itemName,
                    'total_price' => 0,
                ];
            }
            $structured['items'] = $items;
            unset($flat['purchase_items']);
        }

        // Map vendors
        if (isset($flat['vendor'])) {
            $structured['vendors'] = is_array($flat['vendor']) ? $flat['vendor'] : [$flat['vendor']];
            unset($flat['vendor']);
        } elseif (isset($flat['vendors'])) {
            $structured['vendors'] = $flat['vendors'];
            unset($flat['vendors']);
        }

        // Map summary
        if (isset($flat['summary'])) {
            $structured['summary'] = $flat['summary'];
            unset($flat['summary']);
        }

        // Map remaining fields
        foreach ($flat as $key => $value) {
            if (! isset($structured[$key])) {
                $structured[$key] = $value;
            }
        }

        return $structured;
    }
}
