<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\V1\SearchRequest;
use App\Models\SavedSearch;
use App\Services\Search\SearchFilterBuilder;
use App\Services\SearchService;
use Inertia\Inertia;

class SearchController extends Controller
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function search(SearchRequest $request)
    {
        $isInertiaRequest = $request->header('X-Inertia');
        $query = $request->input('query', '');

        // Build filters from request via helper
        $filters = SearchFilterBuilder::build($request);

        $searchResults = $this->searchService->search($query, $filters);

        // Return JSON for non-Inertia AJAX/API requests
        if (! $isInertiaRequest && ($request->wantsJson() || $request->ajax())) {
            return response()->json($searchResults);
        }

        // Return Inertia page for direct page loads
        return Inertia::render('Search', [
            'query' => $query,
            'initialFilters' => $request->safe()->except(['query', 'q', 'page', 'limit', 'saved_search']),
            'activeView' => $request->filled('saved_search') ? SavedSearch::query()->where('user_id', $request->user()->id)
                ->find($request->integer('saved_search'))?->only(['id', 'name', 'scope', 'filters', 'is_pinned']) : null,
            'initialSearchStatus' => $searchResults['search_status'],
            'initialResults' => $searchResults['results'] ?? [],
            'initialPagination' => $searchResults['pagination'],
            'initialFacets' => $searchResults['facets'] ?? [
                'total' => 0, 'receipts' => 0, 'documents' => 0,
                'invoices' => 0, 'contracts' => 0, 'vouchers' => 0,
                'warranties' => 0, 'return_policies' => 0, 'bank_statements' => 0,
            ],
        ]);
    }
}
