<?php

namespace App\Services;

use App\Services\Search\SearchFacetService;
use App\Services\Search\SearchQueryBuilder;
use Exception;

class SearchService
{
    public function __construct(
        protected SearchQueryBuilder $queryBuilder,
        protected SearchFacetService $facetService,
    ) {}

    /**
     * Search across all content types.
     */
    public function search(string $query, array $filters = []): array
    {
        if (trim($query) === '' && ! $this->facetService->hasActiveFilters($filters)) {
            return [
                'results' => [],
                'facets' => SearchFacetService::EMPTY_FACETS,
            ];
        }

        $type = $filters['type'] ?? 'all';
        $results = collect();

        $typeSearchMap = [
            'receipt' => fn () => $this->queryBuilder->searchReceipts($query, $filters),
            'document' => fn () => $this->queryBuilder->searchDocuments($query, $filters),
            'invoice' => fn () => $this->queryBuilder->searchInvoices($query, $filters),
            'contract' => fn () => $this->queryBuilder->searchContracts($query, $filters),
            'voucher' => fn () => $this->queryBuilder->searchVouchers($query, $filters),
            'warranty' => fn () => $this->queryBuilder->searchWarranties($query, $filters),
            'return_policy' => fn () => $this->queryBuilder->searchReturnPolicies($query, $filters),
            'bank_statement' => fn () => $this->queryBuilder->searchBankStatements($query, $filters),
        ];

        foreach ($typeSearchMap as $typeKey => $searchFn) {
            if ($type === 'all' || $type === $typeKey) {
                try {
                    $results = $results->concat($searchFn());
                } catch (Exception $e) {
                    report($e);
                }
            }
        }

        // Sort by relevance/date
        $results = $results->sortByDesc(function ($item) {
            return $item['score'] ?? 0;
        });

        // Apply pagination if needed
        if (isset($filters['limit'])) {
            $results = $results->take($filters['limit']);
        }

        return [
            'results' => $results->values()->all(),
            'facets' => $this->facetService->buildFacets($query, $filters),
        ];
    }
}
