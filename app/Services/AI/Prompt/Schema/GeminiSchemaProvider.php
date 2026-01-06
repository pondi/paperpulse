<?php

namespace App\Services\AI\Prompt\Schema;

/**
 * Provides Gemini-specific multi-entity schemas.
 *
 * Single Responsibility: Map document types to their multi-entity schema configurations.
 * Uses MultiEntitySchemaBuilder to wrap OpenAI schemas (DRY principle).
 */
class GeminiSchemaProvider
{
    /**
     * Get the multi-entity schema and prompt for a document type.
     *
     * Returns a schema that follows OpenAI structure for individual entities,
     * wrapped in Gemini's multi-entity format for multi-entity extraction.
     *
     * @param  string  $type  Primary document type (receipt, invoice, contract, etc.)
     * @param  string|null  $subtype  Optional subtype for specialized handling
     * @return array Schema configuration with responseSchema and prompt
     */
    public function forType(string $type, ?string $subtype = null): array
    {
        $config = match ($type) {
            'receipt' => [
                'name' => 'receipt_multi_entity',
                'primary' => 'receipt',
                'additional' => ['voucher', 'warranty', 'return_policy'],
                'prompt' => $this->getReceiptPrompt(),
            ],
            'invoice' => [
                'name' => 'invoice_multi_entity',
                'primary' => 'invoice',
                'additional' => ['invoice_line_items', 'voucher'],
                'prompt' => $this->getInvoicePrompt(),
            ],
            'contract' => [
                'name' => 'contract',
                'primary' => 'contract',
                'additional' => [],
                'prompt' => $this->getContractPrompt(),
            ],
            'bank_statement' => [
                'name' => 'bank_statement_multi_entity',
                'primary' => 'bank_statement',
                'additional' => ['bank_transactions'],
                'prompt' => $this->getBankStatementPrompt(),
            ],
            'document' => [
                'name' => 'document',
                'primary' => 'document',
                'additional' => [],
                'prompt' => $this->getDocumentPrompt(),
            ],
            default => [
                'name' => 'document',
                'primary' => 'document',
                'additional' => [],
                'prompt' => $this->getDocumentPrompt(),
            ],
        };

        // Build the multi-entity responseSchema
        $responseSchema = MultiEntitySchemaBuilder::forDocumentType(
            $config['primary'],
            $config['additional']
        );

        return [
            'type' => $type,
            'subtype' => $subtype,
            'name' => $config['name'],
            'primary_entity' => $config['primary'],
            'additional_entities' => $config['additional'],
            'prompt' => $config['prompt'],
            'responseSchema' => $responseSchema,
        ];
    }

    /**
     * Get receipt extraction prompt.
     * Instructs the model on what to extract, knowing the schema enforces the structure.
     */
    protected function getReceiptPrompt(): string
    {
        return <<<'PROMPT'
Extract all information from this receipt document.

WHAT TO EXTRACT:
1. receipt - Always include the main receipt entity with:
   - Merchant information (name, address, VAT number, contact details)
   - Receipt metadata (date, time, receipt number)
   - All line items purchased (every item on the receipt)
   - Totals (subtotal, tax, discounts, final amount)
   - Payment details if present
   - Summary (1-2 sentence description of purchase)
   - Vendors/brands mentioned on items

2. ADDITIONAL ENTITIES (if present on this receipt):
   - voucher: Gift cards, store credit, promotional codes with expiry dates
   - warranty: Product warranty information with coverage details
   - return_policy: Return/exchange policy details and deadlines

IMPORTANT:
- Always extract the receipt as the first entity
- Extract additional entities ONLY if they are explicitly present
- Use the exact structure defined in the schema
- Include a confidence_score (0.0 to 1.0) for every extracted entity
- Follow Norwegian conventions for dates (YYYY-MM-DD), amounts, and VAT numbers
- Identify product vendors/brands (e.g., "Garmin", "Apple", "Samsung")
PROMPT;
    }

    protected function getInvoicePrompt(): string
    {
        return view('ai.prompts.invoice-gemini')->render();
    }

    protected function getContractPrompt(): string
    {
        return view('ai.prompts.contract-gemini')->render();
    }

    protected function getBankStatementPrompt(): string
    {
        return view('ai.prompts.bank-statement-gemini')->render();
    }

    protected function getDocumentPrompt(): string
    {
        return 'Analyze this document thoroughly. Extract the title, classify the type, generate a summary, identify key entities (people, organizations, locations, dates), suggest relevant tags, and detect the language. Focus on accuracy and completeness. Include a confidence_score (0.0-1.0) for the extraction.';
    }
}
