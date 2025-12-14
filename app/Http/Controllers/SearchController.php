<?php

namespace App\Http\Controllers;

use App\Services\Search\SearchFilterBuilder;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController extends Controller
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function search(Request $request)
    {
        $isInertiaRequest = $request->header('X-Inertia');
        $query = $request->input('query', '');

        // Build filters from request via helper
        $filters = SearchFilterBuilder::build($request);

        // If no query and no filters, return empty results
        if (empty($query) && empty(array_filter($filters))) {
            $searchResults = ['results' => [], 'facets' => ['total' => 0, 'receipts' => 0, 'documents' => 0]];
        } else {
            $searchResults = $this->searchService->search($query, $filters);
        }

        // Return JSON for non-Inertia AJAX/API requests
        if (! $isInertiaRequest && ($request->wantsJson() || $request->ajax())) {
            return response()->json($searchResults);
        }

        // Return Inertia page for direct page loads
        return Inertia::render('Search', [
            'query' => $query,
            'initialResults' => $searchResults['results'] ?? [],
            'initialFacets' => $searchResults['facets'] ?? ['total' => 0, 'receipts' => 0, 'documents' => 0],
        ]);
    }
}
