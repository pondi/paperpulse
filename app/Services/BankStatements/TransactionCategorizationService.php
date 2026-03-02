<?php

declare(strict_types=1);

namespace App\Services\BankStatements;

use App\Contracts\Services\TextAnalysisContract;
use App\Enums\TransactionCategory;
use App\Models\BankTransaction;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TransactionCategorizationService
{
    private const BATCH_SIZE = 50;

    public function __construct(
        protected TextAnalysisContract $ai
    ) {}

    /**
     * Categorize a collection of bank transactions using AI.
     */
    public function categorize(Collection $transactions): void
    {
        if ($transactions->isEmpty()) {
            return;
        }

        $transactions->chunk(self::BATCH_SIZE)->each(function (Collection $batch) {
            $this->categorizeBatch($batch);
        });
    }

    /**
     * Categorize a single batch of transactions via AI.
     */
    protected function categorizeBatch(Collection $batch): void
    {
        $categories = collect(TransactionCategory::cases())
            ->map(fn (TransactionCategory $c) => "{$c->value}: {$c->label()}")
            ->implode(', ');

        $subcategories = collect(TransactionCategory::subcategories())
            ->map(fn (array $subs, string $group) => "{$group}: ".implode(', ', $subs))
            ->implode("\n");

        $transactionLines = $batch->map(function (BankTransaction $tx) {
            $type = $tx->transaction_type ?? 'unknown';
            $amount = $tx->amount ?? 0;

            return "ID:{$tx->id}|{$tx->description}|{$type}|{$amount}";
        })->implode("\n");

        $prompt = <<<PROMPT
        You are a financial transaction categorization expert.
        Categorize each bank transaction below into a category_group and subcategory.
        The transaction descriptions may be in ANY language — analyze meaning, not just keywords.

        Available category groups (use the exact values on the left of the colon):
        {$categories}

        Subcategories per group:
        {$subcategories}

        Transactions to categorize (format: ID|Description|Type|Amount):
        {$transactionLines}

        Return a JSON array of objects: [{"id": <id>, "category_group": "<group_value>", "subcategory": "<subcategory>"}]

        Rules:
        - Use the exact enum values for category_group (e.g., "food_and_drink", not "Food & Drink")
        - Pick the most specific subcategory that fits
        - If unsure, pick the closest match rather than leaving it empty
        - Every transaction MUST have a category_group
        - Return one entry per transaction ID
        PROMPT;

        try {
            $result = $this->ai->analyze($prompt);

            $items = isset($result[0]) ? $result : [$result];

            $this->applyResults($items, $batch);
        } catch (Exception $e) {
            Log::warning('[TransactionCategorizationService] AI categorization failed', [
                'error' => $e->getMessage(),
                'batch_size' => $batch->count(),
                'provider' => $this->ai->getProviderName(),
            ]);
        }
    }

    /**
     * Apply AI categorization results to transactions.
     *
     * @param  list<array{id: int, category_group: string, subcategory: string}>  $results
     */
    protected function applyResults(array $results, Collection $batch): void
    {
        $resultMap = collect($results)->keyBy('id');

        foreach ($batch as $transaction) {
            $result = $resultMap->get($transaction->id);

            if (! $result || ! isset($result['category_group'])) {
                continue;
            }

            $category = TransactionCategory::tryFrom($result['category_group']);

            if ($category) {
                $transaction->update([
                    'category_group' => $category,
                    'subcategory' => $result['subcategory'] ?? null,
                ]);
            }
        }
    }
}
