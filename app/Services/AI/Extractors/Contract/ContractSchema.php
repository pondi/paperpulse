<?php

namespace App\Services\AI\Extractors\Contract;

/**
 * Simplified contract schema for Gemini extraction.
 *
 * Flattened to 2-3 levels max to avoid Gemini API 400 errors from deep nesting.
 */
class ContractSchema
{
    /**
     * Get simplified contract schema.
     *
     * @return array Schema configuration with responseSchema key
     */
    public static function get(): array
    {
        return [
            'name' => 'contract_extraction',
            'responseSchema' => [
                'type' => 'object',
                'properties' => [
                    // Contract identification (flattened)
                    'contract_number' => ['type' => 'string', 'description' => 'Contract reference/agreement number'],
                    'contract_title' => ['type' => 'string', 'description' => 'Official contract title'],
                    'contract_type' => ['type' => 'string', 'description' => 'Contract type (NDA, Service Agreement, License, Purchase, Employment, Lease, etc.)'],

                    // Parties (simplified to 2 levels: array → party properties)
                    'parties' => [
                        'type' => 'array',
                        'description' => 'Contract parties involved',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => 'Party name/company'],
                                'role' => ['type' => 'string', 'description' => 'Party role (buyer, seller, vendor, client, service provider, etc.)'],
                                'contact' => ['type' => 'string', 'description' => 'Contact information (email, phone, address)'],
                                'registration_number' => ['type' => 'string', 'description' => 'Business/VAT/Company registration number'],
                            ],
                            'required' => ['name'],
                        ],
                    ],

                    // Dates (flattened)
                    'effective_date' => ['type' => 'string', 'description' => 'Contract effective/start date (YYYY-MM-DD)'],
                    'expiry_date' => ['type' => 'string', 'description' => 'Contract expiry/end date (YYYY-MM-DD)'],
                    'signature_date' => ['type' => 'string', 'description' => 'Date contract was signed (YYYY-MM-DD)'],

                    // Contract terms (flattened)
                    'duration' => ['type' => 'string', 'description' => 'Contract duration/term (e.g., "12 months", "2 years")'],
                    'renewal_terms' => ['type' => 'string', 'description' => 'Renewal conditions (automatic, manual, notice period, etc.)'],

                    // Financial terms (flattened)
                    'contract_value' => ['type' => 'number', 'description' => 'Total contract value/amount'],
                    'currency' => ['type' => 'string', 'description' => 'Currency code (NOK, EUR, USD, GBP, etc.)'],
                    'payment_schedule' => [
                        'type' => 'array',
                        'description' => 'Payment schedule details',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'milestone' => ['type' => 'string', 'description' => 'Payment milestone/condition'],
                                'amount' => ['type' => 'number', 'description' => 'Amount for this milestone'],
                                'date' => ['type' => 'string', 'description' => 'Payment date (YYYY-MM-DD)'],
                            ],
                        ],
                    ],

                    // Legal terms (flattened)
                    'governing_law' => ['type' => 'string', 'description' => 'Jurisdiction for governing law (e.g., "Norwegian law", "English law")'],
                    'jurisdiction' => ['type' => 'string', 'description' => 'Court jurisdiction for disputes'],
                    'termination_conditions' => ['type' => 'string', 'description' => 'How contract can be terminated (notice period, conditions)'],

                    // Key obligations (simplified array)
                    'key_obligations' => [
                        'type' => 'array',
                        'description' => 'Key obligations of parties',
                        'items' => ['type' => 'string'],
                    ],

                    // Summary and metadata
                    'summary' => ['type' => 'string', 'description' => '2-3 sentence summary of contract purpose'],
                    'status' => ['type' => 'string', 'description' => 'Contract status (active, expired, pending, terminated)'],
                    'confidence_score' => ['type' => 'number', 'description' => 'Extraction confidence (0.0-1.0)'],
                ],
                'required' => ['contract_title', 'effective_date', 'parties'],
            ],
        ];
    }

    /**
     * Get extraction prompt.
     */
    public static function getPrompt(): string
    {
        return <<<'PROMPT'
Extract all contract information from this document.

**What to extract:**

1. **Contract identification**: Contract number, official title, type (NDA, Service Agreement, License, etc.)
2. **Parties**: All parties involved with their roles and contact information
3. **Dates**: Effective date (start), expiry/end date, signature date (all YYYY-MM-DD format)
4. **Duration**: Contract term/duration and renewal conditions
5. **Financial terms**: Contract value, currency, payment schedule with milestones
6. **Legal terms**: Governing law, jurisdiction, termination conditions
7. **Key obligations**: Main obligations required by each party
8. **Summary**: 2-3 sentence description of what the contract covers
9. **Status**: Whether contract is active, expired, pending, or terminated

**Important:**
- Extract ALL parties, not just a sample
- Use YYYY-MM-DD format for all dates
- Include confidence_score (0.0-1.0) based on document clarity
- Party roles could be: vendor, service provider, buyer, seller, client, employer, employee, licensor, licensee, lessor, lessee, etc.
- If a field is not present or unclear, omit it (don't guess)

**Example structure:**
```json
{
  "contract_number": "AGR-2024-001",
  "contract_title": "Software License Agreement",
  "contract_type": "License",
  "parties": [
    {"name": "TechCorp AS", "role": "licensor", "contact": "legal@techcorp.no", "registration_number": "123456789"},
    {"name": "ClientCorp AB", "role": "licensee", "contact": "contracts@clientcorp.se"}
  ],
  "effective_date": "2024-01-15",
  "expiry_date": "2025-01-14",
  "signature_date": "2024-01-10",
  "duration": "12 months",
  "renewal_terms": "Automatic renewal unless terminated with 30 days notice",
  "contract_value": 50000,
  "currency": "EUR",
  "payment_schedule": [
    {"milestone": "Upon execution", "amount": 25000, "date": "2024-01-15"},
    {"milestone": "Final payment", "amount": 25000, "date": "2024-12-15"}
  ],
  "governing_law": "Norwegian law",
  "jurisdiction": "Oslo District Court",
  "termination_conditions": "Either party may terminate with 30 days written notice",
  "key_obligations": [
    "Licensor to provide technical support",
    "Licensee to pay fees on schedule",
    "Licensee must not sublicense without permission"
  ],
  "summary": "TechCorp grants ClientCorp a 12-month software license with technical support. Annual fees of 50,000 EUR payable in two installments.",
  "status": "active",
  "confidence_score": 0.92
}
```
PROMPT;
    }
}
