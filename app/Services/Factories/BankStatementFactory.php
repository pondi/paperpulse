<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\File;
use App\Services\Factories\Concerns\ChecksDataPresence;
use Illuminate\Database\Eloquent\Model;

class BankStatementFactory extends BaseEntityFactory
{
    use ChecksDataPresence;

    protected function modelClass(): string
    {
        return BankStatement::class;
    }

    protected function fields(): array
    {
        return [
            'bank_name',
            'account_holder_name',
            'account_number',
            'iban',
            'swift_code',
            'statement_date',
            'statement_period_start',
            'statement_period_end',
            'opening_balance',
            'closing_balance',
            'currency',
            'total_credits',
            'total_debits',
            'transaction_count',
        ];
    }

    protected function dateFields(): array
    {
        return ['statement_date', 'statement_period_start', 'statement_period_end'];
    }

    protected function rawDataField(): ?string
    {
        return 'statement_data';
    }

    protected function shouldCreate(array $data): bool
    {
        return $this->hasAny($data, ['account_number', 'iban', 'bank_name', 'statement_date']);
    }

    protected function prepareData(array $data, File $file): array
    {
        return $this->flattenData($data);
    }

    protected function afterCreate(Model $model, array $data, File $file): void
    {
        if (! empty($data['transactions'])) {
            $this->createTransactions($data['transactions'], $model);
        }
    }

    /**
     * Create transactions for a bank statement.
     *
     * @param  array<int, array<string, mixed>>  $transactions
     * @return array<int, BankTransaction>
     */
    public function createTransactions(array $transactions, BankStatement $statement): array
    {
        $created = [];
        $totalCredits = 0;
        $totalDebits = 0;

        foreach ($transactions as $txn) {
            $txnDate = $this->nullIfEmpty($txn['transaction_date'] ?? $txn['date'] ?? null);
            $postingDate = $this->nullIfEmpty($txn['posting_date'] ?? null);

            $created[] = BankTransaction::create([
                'bank_statement_id' => $statement->id,
                'user_id' => $statement->user_id,
                'transaction_date' => $txnDate,
                'posting_date' => $postingDate,
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
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
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
