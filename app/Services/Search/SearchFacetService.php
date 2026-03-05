<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Voucher;
use App\Models\Warranty;
use Exception;
use Illuminate\Support\Facades\Cache;

/**
 * Computes facet counts and aggregations for search results.
 */
class SearchFacetService
{
    public const EMPTY_FACETS = [
        'total' => 0,
        'receipts' => 0,
        'documents' => 0,
        'invoices' => 0,
        'contracts' => 0,
        'vouchers' => 0,
        'warranties' => 0,
        'return_policies' => 0,
        'bank_statements' => 0,
    ];

    public function buildFacets(string $query, array $filters): array
    {
        if (trim($query) === '' && ! $this->hasActiveFilters($filters)) {
            return self::EMPTY_FACETS;
        }

        $userId = auth()->id();
        $cacheKey = "search_facets:{$userId}:".md5($query.serialize($filters));

        return Cache::remember($cacheKey, 60, function () use ($query, $filters, $userId) {
            return $this->computeFacets($query, $filters, $userId);
        });
    }

    protected function computeFacets(string $query, array $filters, int $userId): array
    {
        $queries = [
            'receipts' => Receipt::search($query)->where('user_id', $userId),
            'documents' => Document::search($query)->where('user_id', $userId),
            'invoices' => Invoice::search($query)->where('user_id', $userId),
            'contracts' => Contract::search($query)->where('user_id', $userId),
            'vouchers' => Voucher::search($query)->where('user_id', $userId),
            'warranties' => Warranty::search($query)->where('user_id', $userId),
            'return_policies' => ReturnPolicy::search($query)->where('user_id', $userId),
            'bank_statements' => BankStatement::search($query)->where('user_id', $userId),
        ];

        $dateFieldMap = [
            'receipts' => 'receipt_date',
            'documents' => 'created_at',
            'invoices' => 'invoice_date',
            'contracts' => 'effective_date',
            'vouchers' => 'expiry_date',
            'warranties' => 'warranty_end_date',
            'return_policies' => 'return_deadline',
            'bank_statements' => 'statement_date',
        ];

        foreach ($queries as $type => $searchQuery) {
            $dateField = $dateFieldMap[$type];
            if (isset($filters['date_from'])) {
                $searchQuery->where($dateField, '>=', $filters['date_from']);
            }
            if (isset($filters['date_to'])) {
                $searchQuery->where($dateField, '<=', $filters['date_to']);
            }
        }

        $counts = [];
        $total = 0;

        foreach ($queries as $type => $searchQuery) {
            try {
                $count = $searchQuery->raw()['estimatedTotalHits'] ?? 0;
            } catch (Exception $e) {
                $count = 0;
            }
            $counts[$type] = $count;
            $total += $count;
        }

        return ['total' => $total, ...$counts];
    }

    public function hasActiveFilters(array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if ($key === 'limit') {
                continue;
            }

            if ($key === 'type') {
                if (is_string($value) && $value !== '' && $value !== 'all') {
                    return true;
                }

                continue;
            }

            if (is_array($value)) {
                if (! empty(array_filter($value, fn ($v) => $v !== null && $v !== ''))) {
                    return true;
                }

                continue;
            }

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
