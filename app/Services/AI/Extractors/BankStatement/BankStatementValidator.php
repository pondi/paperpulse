<?php

namespace App\Services\AI\Extractors\BankStatement;

/**
 * Validates extracted bank statement data.
 */
class BankStatementValidator
{
    /**
     * Validate bank statement data.
     *
     * @param  array  $data  Extracted bank statement data
     * @return array {valid: bool, errors: array, warnings: array}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $warnings = [];

        // Required fields
        if (empty($data['bank_name'])) {
            $errors[] = 'Missing bank name';
        }

        if (empty($data['account_number'])) {
            $errors[] = 'Missing account number';
        }

        if (empty($data['statement_period_start'])) {
            $errors[] = 'Missing statement period start date';
        }

        if (empty($data['statement_period_end'])) {
            $errors[] = 'Missing statement period end date';
        }

        // Validate date formats
        if (! empty($data['statement_period_start']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['statement_period_start'])) {
            $warnings[] = 'Statement period start date not in YYYY-MM-DD format';
        }

        if (! empty($data['statement_period_end']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['statement_period_end'])) {
            $warnings[] = 'Statement period end date not in YYYY-MM-DD format';
        }

        // Validate balances are numeric
        if (isset($data['opening_balance']) && ! is_numeric($data['opening_balance'])) {
            $errors[] = 'Opening balance must be numeric';
        }

        if (isset($data['closing_balance']) && ! is_numeric($data['closing_balance'])) {
            $errors[] = 'Closing balance must be numeric';
        }

        // Validate transaction data
        if (isset($data['transactions']) && is_array($data['transactions'])) {
            foreach ($data['transactions'] as $idx => $transaction) {
                if (empty($transaction['date'])) {
                    $warnings[] = "Transaction {$idx}: Missing date";
                } elseif (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $transaction['date'])) {
                    $warnings[] = "Transaction {$idx}: Date not in YYYY-MM-DD format";
                }

                if (empty($transaction['description'])) {
                    $warnings[] = "Transaction {$idx}: Missing description";
                }

                if (! isset($transaction['amount']) || ! is_numeric($transaction['amount'])) {
                    $warnings[] = "Transaction {$idx}: Missing or non-numeric amount";
                }
            }
        }

        // Check confidence score
        if (isset($data['confidence_score']) && $data['confidence_score'] < 0.5) {
            $warnings[] = 'Low confidence score: '.$data['confidence_score'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
