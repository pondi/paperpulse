<?php

namespace App\Services\Search;

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Support\Collection;

class UnifiedSearchService
{
    /**
     * Search across all entity types
     */
    public function searchAll(
        string $query,
        int $userId,
        ?array $types = null,
        int $limit = 50
    ): Collection {
        $types = $types ?? [
            'receipt', 'voucher', 'warranty', 'return_policy',
            'invoice', 'contract', 'bank_statement', 'document',
        ];

        $results = collect();

        foreach ($types as $type) {
            $modelClass = $this->getModelClass($type);

            if (! $modelClass) {
                continue;
            }

            $typeResults = $modelClass::search($query)
                ->where('user_id', $userId)
                ->take($limit)
                ->get()
                ->map(fn ($item) => [
                    'type' => $type,
                    'entity' => $item,
                    'score' => $item->searchScore ?? 1.0,
                ]);

            $results = $results->merge($typeResults);
        }

        return $results
            ->sortByDesc('score')
            ->take($limit);
    }

    /**
     * Search a specific entity type
     */
    public function searchType(
        string $type,
        string $query,
        int $userId,
        array $filters = [],
        int $limit = 50
    ): Collection {
        $modelClass = $this->getModelClass($type);

        if (! $modelClass) {
            return collect();
        }

        $search = $modelClass::search($query)
            ->where('user_id', $userId);

        // Apply type-specific filters
        foreach ($filters as $key => $value) {
            $search->where($key, $value);
        }

        return $search->take($limit)->get();
    }

    /**
     * Get model class for entity type
     */
    protected function getModelClass(string $type): ?string
    {
        return match ($type) {
            'receipt' => Receipt::class,
            'voucher' => Voucher::class,
            'warranty' => Warranty::class,
            'return_policy' => ReturnPolicy::class,
            'invoice' => Invoice::class,
            'contract' => Contract::class,
            'bank_statement' => BankStatement::class,
            'document' => Document::class,
            default => null
        };
    }

    /**
     * Get search suggestions across all entity types
     */
    public function getSuggestions(
        string $query,
        int $userId,
        int $limit = 5
    ): Collection {
        return $this->searchAll($query, $userId, null, $limit)
            ->map(fn ($result) => [
                'type' => $result['type'],
                'id' => $result['entity']->id,
                'title' => $this->getEntityTitle($result['entity'], $result['type']),
                'subtitle' => $this->getEntitySubtitle($result['entity'], $result['type']),
            ]);
    }

    /**
     * Get display title for entity
     *
     * @param  mixed  $entity
     */
    protected function getEntityTitle($entity, string $type): string
    {
        return match ($type) {
            'receipt' => $entity->merchant?->name ?? 'Receipt',
            'voucher' => $entity->code ?? 'Voucher',
            'warranty' => $entity->product_name ?? 'Warranty',
            'return_policy' => $entity->merchant?->name ?? 'Return Policy',
            'invoice' => 'Invoice #'.$entity->invoice_number,
            'contract' => $entity->contract_title ?? 'Contract',
            'bank_statement' => $entity->bank_name ?? 'Bank Statement',
            'document' => $entity->title ?? 'Document',
            default => 'Unknown'
        };
    }

    /**
     * Get display subtitle for entity
     *
     * @param  mixed  $entity
     */
    protected function getEntitySubtitle($entity, string $type): string
    {
        return match ($type) {
            'receipt' => $entity->receipt_date?->format('Y-m-d'),
            'voucher' => $entity->expiry_date?->format('Y-m-d'),
            'warranty' => $entity->warranty_end_date?->format('Y-m-d'),
            'return_policy' => $entity->return_deadline?->format('Y-m-d'),
            'invoice' => $entity->invoice_date?->format('Y-m-d'),
            'contract' => $entity->effective_date?->format('Y-m-d'),
            'bank_statement' => $entity->statement_date?->format('Y-m-d'),
            'document' => $entity->created_at?->format('Y-m-d'),
            default => ''
        };
    }
}
