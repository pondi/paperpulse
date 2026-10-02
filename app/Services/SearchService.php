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
                'search_status' => 'available',
                'unavailable_types' => [],
                'pagination' => ['page' => 1, 'per_page' => (int) ($filters['limit'] ?? 20), 'total' => 0, 'last_page' => 0],
            ];
        }

        $type = $filters['type'] ?? 'all';
        $results = collect();
        $total = 0;
        $successfulTypes = 0;
        $unavailableTypes = [];
        $page = (int) ($filters['page'] ?? 1);
        $limit = (int) ($filters['limit'] ?? 20);

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
                    $response = $searchFn();
                    $results = $results->concat($response['results']);
                    $total += $response['total'];
                    $successfulTypes++;
                } catch (Exception $e) {
                    $unavailableTypes[] = $typeKey;
                    report($e);
                }
            }
        }

        $results = $results->sortBy([
            fn (array $a, array $b): int => ($b['_rankingScore'] ?? 0) <=> ($a['_rankingScore'] ?? 0),
            fn (array $a, array $b): int => $a['type'] <=> $b['type'],
            fn (array $a, array $b): int => $a['id'] <=> $b['id'],
        ])->slice(($page - 1) * $limit, $limit);

        try {
            $facets = $this->facetService->buildFacets($query, $filters);
            $facetsAvailable = true;
        } catch (Exception $e) {
            report($e);
            $facets = null;
            $facetsAvailable = false;
        }

        $status = $unavailableTypes === [] && $facetsAvailable ? 'available' : ($successfulTypes > 0 ? 'partial' : 'unavailable');

        return [
            'results' => $results->values()->all(),
            'facets' => $facets,
            'search_status' => $status,
            'unavailable_types' => $unavailableTypes,
            'pagination' => [
                'page' => $page, 'per_page' => $limit, 'total' => $total,
                'last_page' => min(20, (int) ceil($total / $limit)),
            ],
        ];
    }
}
