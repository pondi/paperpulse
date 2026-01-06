<?php

namespace App\Services\AI\Extractors\BankStatement;

/**
 * Simplified bank statement schema for Gemini extraction.
 *
 * Flattened to 2-3 levels max to avoid Gemini API 400 errors from deep nesting.
 */
class BankStatementSchema
{
    /**
     * Get simplified bank statement schema.
     *
     * @return array Schema configuration with responseSchema key
     */
    public static function get(): array
    {
        return [
            'name' => 'bank_statement_extraction',
            'responseSchema' => [
                'type' => 'object',
                'properties' => [
                    // Bank info (flattened - no nested object)
                    'bank_name' => ['type' => 'string', 'description' => 'Name of the bank'],
                    'account_holder' => ['type' => 'string', 'description' => 'Name of account holder'],
                    'account_number' => ['type' => 'string', 'description' => 'Account number (can be IBAN or local format)'],

                    // Statement period (flattened)
                    'statement_period_start' => ['type' => 'string', 'description' => 'Statement start date (YYYY-MM-DD)'],
                    'statement_period_end' => ['type' => 'string', 'description' => 'Statement end date (YYYY-MM-DD)'],

                    // Balances (flattened)
                    'opening_balance' => ['type' => 'number', 'description' => 'Opening balance amount'],
                    'closing_balance' => ['type' => 'number', 'description' => 'Closing balance amount'],
                    'currency' => ['type' => 'string', 'description' => 'Currency code (NOK, EUR, USD, etc.)'],

                    // Transactions (simplified to 2 levels: array → transaction properties)
                    'transactions' => [
                        'type' => 'array',
                        'description' => 'List of transactions',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'date' => ['type' => 'string', 'description' => 'Transaction date (YYYY-MM-DD)'],
                                'description' => ['type' => 'string', 'description' => 'Transaction description/reference'],
                                'amount' => ['type' => 'number', 'description' => 'Transaction amount (positive for deposits, negative for withdrawals)'],
                                'balance' => ['type' => 'number', 'description' => 'Balance after transaction'],
                                'transaction_type' => ['type' => 'string', 'description' => 'Type of transaction (debit, credit, transfer, fee, interest)'],
                            ],
                            'required' => ['date', 'description', 'amount'],
                        ],
                    ],

                    // Metadata
                    'confidence_score' => ['type' => 'number', 'description' => 'Extraction confidence (0.0-1.0)'],
                ],
                'required' => ['bank_name', 'account_number', 'statement_period_start', 'statement_period_end'],
            ],
        ];
    }

    /**
     * Get extraction prompt.
     */
    public static function getPrompt(): string
    {
        return <<<'PROMPT'
Extract all bank statement information from this document.

**What to extract:**

1. **Bank details**: Bank name, account holder name, account number
2. **Statement period**: Start date (YYYY-MM-DD), end date (YYYY-MM-DD)
3. **Balances**: Opening balance, closing balance, currency
4. **All transactions**: Every transaction with date, description, amount, balance, and type
5. **Confidence**: Extraction confidence score (0.0-1.0)

**Important:**
- Extract ALL transactions, not just a sample
- Use YYYY-MM-DD format for all dates
- For amounts: positive for deposits/credits, negative for withdrawals/debits
- Account numbers can be IBAN, local format, or abbreviated
- Transaction type: debit, credit, transfer, fee, interest, or similar
- Include confidence_score based on data clarity
- For Norwegian accounts: currency is NOK
- If a field is not present, omit it (don't guess)

**Example structure:**
```json
{
  "bank_name": "DNB Bank",
  "account_holder": "John Doe",
  "account_number": "1234.56.78901",
  "statement_period_start": "2025-01-01",
  "statement_period_end": "2025-01-31",
  "opening_balance": 50000,
  "closing_balance": 48500,
  "currency": "NOK",
  "transactions": [
    {"date": "2025-01-02", "description": "Salary deposit", "amount": 45000, "balance": 95000, "transaction_type": "credit"},
    {"date": "2025-01-05", "description": "Rent payment", "amount": -15000, "balance": 80000, "transaction_type": "debit"},
    {"date": "2025-01-10", "description": "Interest", "amount": 50, "balance": 80050, "transaction_type": "interest"}
  ],
  "confidence_score": 0.95
}
```
PROMPT;
    }
}
