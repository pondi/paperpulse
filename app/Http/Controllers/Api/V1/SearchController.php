<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Services\Search\SearchFilterBuilder;
use App\Services\SearchService;

class SearchController extends BaseApiController
{
    public function __construct(private readonly SearchService $searchService) {}

    public function index(SearchRequest $request)
    {
        $query = (string) ($request->input('q') ?? $request->input('query') ?? '');
        $filters = SearchFilterBuilder::build($request);

        $searchResults = $this->searchService->search($query, $filters);

        $results = collect($searchResults['results'] ?? [])->map(function (array $result) {
            $file = is_array($result['file'] ?? null) ? $result['file'] : null;
            $fileId = $file['id'] ?? null;
            $hasPreview = (bool) ($file['has_image_preview'] ?? false);
            $hasPdf = (bool) ($file['has_archive_pdf'] ?? false)
                || strtolower((string) ($file['extension'] ?? '')) === 'pdf';

            return [
                'id' => $result['id'] ?? null,
                'type' => $result['type'] ?? null,
                'title' => $result['title'] ?? null,
                'snippet' => $result['description'] ?? null,
                'date' => $result['date'] ?? null,
                'filename' => $result['filename'] ?? ($file['filename'] ?? null),
                'total' => $result['total'] ?? null,
                'document_type' => $result['document_type'] ?? null,
                'file' => $file ? [
                    'id' => $fileId,
                    'guid' => $file['guid'] ?? null,
                    'extension' => $file['extension'] ?? null,
                    'has_image_preview' => (bool) ($file['has_image_preview'] ?? false),
                    'has_archive_pdf' => (bool) ($file['has_archive_pdf'] ?? false),
                ] : null,
                'links' => $fileId ? [
                    'content' => route('api.files.content', ['file' => $fileId]),
                    'preview' => $hasPreview ? route('api.files.content', ['file' => $fileId]).'?variant=preview' : null,
                    'pdf' => $hasPdf ? route('api.files.content', ['file' => $fileId]).'?variant=archive' : null,
                ] : null,
            ];
        })->values()->all();

        return $this->success([
            'query' => $query,
            'filters' => $filters,
            'results' => $results,
            'facets' => $searchResults['facets'] ?? ['total' => 0, 'receipts' => 0, 'documents' => 0],
        ], 'Search results retrieved successfully');
    }
}
