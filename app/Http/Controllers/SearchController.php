<?php

namespace App\Http\Controllers;

use App\Services\Search\SearchFilterBuilder;
use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        if (empty($query)) {
            return response()->json(['results' => [], 'facets' => []]);
        }

        // Build filters from request via helper
        $filters = SearchFilterBuilder::build($request);

        $searchResults = $this->searchService->search($query, $filters);

        return response()->json($searchResults);
    }
}
