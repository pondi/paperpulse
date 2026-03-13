<?php

namespace App\Services\AI\TypeClassification;

/**
 * Flat classification schema for Pass 1 (document type detection).
 *
 * Only 2 levels deep to avoid Gemini API 400 errors from deep nesting.
 */
class ClassificationSchema
{
    /**
     * Get the classification schema for Gemini.
     *
     * @return array Schema configuration with responseSchema key
     */
    public static function get(): array
    {
        return [
            'name' => 'document_classification',
            'responseSchema' => [
                'type' => 'object',
                'properties' => [
                    'document_type' => [
                        'type' => 'string',
                        'enum' => [
                            'receipt',
                            'invoice',
                            'voucher',
                            'warranty',
                            'return_policy',
                            'contract',
                            'bank_statement',
                            'document',
                            'unknown',
                        ],
                        'description' => 'The primary type of this document',
                    ],
                    'confidence' => [
                        'type' => 'number',
                        'minimum' => 0.0,
                        'maximum' => 1.0,
                        'description' => 'Confidence score for the classification (0.0 = no confidence, 1.0 = very confident)',
                    ],
                    'reasoning' => [
                        'type' => 'string',
                        'description' => 'Brief explanation (1-2 sentences) for why this classification was chosen',
                    ],
                ],
                'required' => ['document_type', 'confidence', 'reasoning'],
            ],
        ];
    }

    /**
     * Get the classification prompt for Gemini.
     *
     * @param  array  $hints  Optional hints like filename, extension
     * @return string Classification prompt
     */
    public static function getPrompt(array $hints = []): string
    {
        $filename = $hints['filename'] ?? 'unknown';
        $extension = $hints['extension'] ?? 'unknown';

        return <<<PROMPT
Classify this document into one of the following types:

- **receipt**: Purchase receipt from a store/restaurant showing items bought, prices, and payment
- **invoice**: Business invoice requesting payment for goods/services rendered. Must be the ACTUAL invoice document with line items, totals, and payment terms — not a reference to one.
- **voucher**: Gift card, store credit, promotional code, or discount voucher
- **warranty**: Product warranty or guarantee information
- **return_policy**: Return/exchange policy information
- **contract**: Legal contract or agreement between parties. Must be the ACTUAL contract/agreement — not a letter or email discussing one.
- **bank_statement**: Bank account statement with transactions
- **document**: Generic document (if none of the above fit)
- **unknown**: Cannot determine type from the content

## CRITICAL CLASSIFICATION RULES

You must classify based on WHAT THE DOCUMENT ITSELF IS, not what it references or discusses:

- An **email** that mentions an invoice, attaches an invoice, or discusses payment → classify as **document**, NOT invoice
- A **letter** that references a contract or agreement → classify as **document**, NOT contract
- A **screenshot** of a conversation about a receipt → classify as **document**, NOT receipt
- A **forwarded email** with bank transaction details → classify as **document**, NOT bank_statement
- A **notification** about a warranty claim → classify as **document**, NOT warranty

The document must BE the actual financial/legal instrument to be classified as that type. If the document is correspondence (email, letter, memo, chat) that merely references or discusses another document type, classify it as **document**.

**Hints:**
- Filename: {$filename}
- Extension: {$extension}

Analyze the document and provide:
1. The most likely document_type from the list above
2. Your confidence score (0.0 to 1.0)
3. Brief reasoning for your classification

Be conservative with confidence scores:
- 0.9-1.0: Very certain (clear indicators present)
- 0.7-0.9: Confident (multiple indicators match)
- 0.5-0.7: Moderate confidence (some indicators, but ambiguous)
- < 0.5: Low confidence (uncertain classification)

Only classify as "unknown" if you genuinely cannot determine the type.
PROMPT;
    }
}
