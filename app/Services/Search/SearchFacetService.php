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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Counts each content type using all content filters. Type and pagination
 * select displayed results, so they do not restrict the type-tab counts.
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
        unset($filters['type'], $filters['page'], $filters['limit']);
        ksort($filters);
        $version = Cache::rememberForever("search_facets:{$userId}:version", static fn () => (string) Str::uuid());
        $cacheKey = "search_facets:{$userId}:{$version}:".md5($query.serialize($filters));

        return Cache::remember($cacheKey, 60, function () use ($query, $filters, $userId) {
            return $this->computeFacets($query, $filters, $userId);
        });
    }

    public static function invalidate(int $userId): void
    {
        Cache::forever("search_facets:{$userId}:version", (string) Str::uuid());
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

        foreach ($queries as $searchQuery) {
            SearchFilterBuilder::apply($searchQuery, $filters);
        }

        $counts = [];
        $total = 0;

        foreach ($queries as $type => $searchQuery) {
            $searchQuery->options(['hitsPerPage' => 1, 'page' => 1, 'attributesToRetrieve' => ['id']]);
            $count = $searchQuery->model->searchableUsing()->getTotalCount($searchQuery->raw());
            $counts[$type] = $count;
            $total += $count;
        }

        return ['total' => $total, ...$counts];
    }

    public function hasActiveFilters(array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if (in_array($key, ['limit', 'page'], true)) {
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
