<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\File;
use App\Services\Factories\Concerns\ChecksDataPresence;

class BankStatementFactory
{
    use ChecksDataPresence;

    public function create(array $data, File $file): ?BankStatement
    {
        $data = $this->flattenData($data);

        if (! $this->hasAny($data, ['account_number', 'iban', 'bank_name', 'statement_date'])) {
            return null;
        }

        $statement = BankStatement::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'bank_name' => $data['bank_name'] ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'iban' => $data['iban'] ?? null,
            'swift_code' => $data['swift_code'] ?? null,
            'statement_date' => $data['statement_date'] ?? null,
            'statement_period_start' => $data['statement_period_start'] ?? null,
            'statement_period_end' => $data['statement_period_end'] ?? null,
            'opening_balance' => $data['opening_balance'] ?? null,
            'closing_balance' => $data['closing_balance'] ?? null,
            'currency' => $data['currency'] ?? null,
            'total_credits' => $data['total_credits'] ?? null,
            'total_debits' => $data['total_debits'] ?? null,
            'transaction_count' => $data['transaction_count'] ?? null,
            'statement_data' => $data['statement_data'] ?? $data,
        ]);

        if (! empty($data['transactions'])) {
            $this->createTransactions($data['transactions'], $statement);
        }

        return $statement;
    }

    public function createTransactions(array $transactions, BankStatement $statement): array
    {
        $created = [];
        $totalCredits = 0;
        $totalDebits = 0;

        foreach ($transactions as $txn) {
            $created[] = BankTransaction::create([
                'bank_statement_id' => $statement->id,
                'user_id' => $statement->user_id,
                'transaction_date' => $txn['transaction_date'] ?? $txn['date'] ?? null,
                'posting_date' => $txn['posting_date'] ?? null,
                'description' => $txn['description'] ?? null,
                'reference' => $txn['reference'] ?? null,
                'transaction_type' => $txn['transaction_type'] ?? null,
                'category' => $txn['category'] ?? null,
                'amount' => $txn['amount'] ?? null,
                'balance_after' => $txn['balance_after'] ?? $txn['balance'] ?? null,
                'currency' => $txn['currency'] ?? $statement->currency,
                'counterparty_name' => $txn['counterparty_name'] ?? null,
                'counterparty_account' => $txn['counterparty_account'] ?? null,
            ]);

            $amount = (float) ($txn['amount'] ?? 0);
            if ($amount > 0) {
                $totalCredits += $amount;
            } else {
                $totalDebits += abs($amount);
            }
        }

        $updates = [];
        if ($statement->total_credits === null) {
            $updates['total_credits'] = round($totalCredits, 2);
        }
        if ($statement->total_debits === null) {
            $updates['total_debits'] = round($totalDebits, 2);
        }
        if ($statement->transaction_count === null) {
            $updates['transaction_count'] = count($created);
        }
        if (! empty($updates)) {
            $statement->update($updates);
        }

        return $created;
    }

    /**
     * Flatten nested bank statement data from the normalizer.
     */
    protected function flattenData(array $data): array
    {
        $hasFlat = isset($data['bank_name']) || isset($data['account_number']);
        $hasNested = isset($data['bank']) || isset($data['balances']);
        if ($hasFlat && ! $hasNested) {
            return $data;
        }

        $flat = [];

        if (isset($data['bank']) && is_array($data['bank'])) {
            $flat['bank_name'] = $data['bank']['name'] ?? null;
            $flat['account_holder_name'] = $data['bank']['account_holder'] ?? null;
            $flat['account_number'] = $data['bank']['account_number'] ?? null;
            $flat['iban'] = $data['bank']['iban'] ?? null;
            $flat['swift_code'] = $data['bank']['swift_code'] ?? null;
        }

        if (isset($data['statement_period']) && is_array($data['statement_period'])) {
            $flat['statement_period_start'] = $data['statement_period']['start_date'] ?? null;
            $flat['statement_period_end'] = $data['statement_period']['end_date'] ?? null;
        }

        if (isset($data['balances']) && is_array($data['balances'])) {
            $flat['opening_balance'] = $data['balances']['opening_balance'] ?? null;
            $flat['closing_balance'] = $data['balances']['closing_balance'] ?? null;
            $flat['currency'] = $data['balances']['currency'] ?? null;
        }

        if (isset($data['transactions'])) {
            $flat['transactions'] = $data['transactions'];
            $flat['transaction_count'] = is_array($data['transactions']) ? count($data['transactions']) : null;
        }

        foreach (['statement_date', 'total_credits', 'total_debits', 'statement_data'] as $key) {
            if (isset($data[$key])) {
                $flat[$key] = $data[$key];
            }
        }

        $flat['statement_data'] = $data;

        return $flat;
    }
}
