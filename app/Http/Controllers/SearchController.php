<?php

namespace App\Http\Controllers;

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

        // Build filters from request
        $filters = [
            'type' => $request->input('type', 'all'),
            'limit' => $request->input('limit', 20),
        ];

        // Add date filters if provided
        if ($request->has('date_from')) {
            $filters['date_from'] = $request->input('date_from');
        }
        if ($request->has('date_to')) {
            $filters['date_to'] = $request->input('date_to');
        }

        // Add amount filters for receipts
        if ($request->has('amount_min')) {
            $filters['amount_min'] = $request->input('amount_min');
        }
        if ($request->has('amount_max')) {
            $filters['amount_max'] = $request->input('amount_max');
        }

        // Add category filter
        if ($request->has('category')) {
            $filters['category'] = $request->input('category');
        }

        // Add document type filter
        if ($request->has('document_type')) {
            $filters['document_type'] = $request->input('document_type');
        }

        // Add tag filters
        if ($request->has('tags')) {
            $filters['tags'] = is_array($request->input('tags'))
                ? $request->input('tags')
                : explode(',', $request->input('tags'));
        }

        $searchResults = $this->searchService->search($query, $filters);

        return response()->json($searchResults);
    }
}
