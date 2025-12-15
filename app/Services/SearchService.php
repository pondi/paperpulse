<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * Search across all content types
     */
    public function search(string $query, array $filters = []): array
    {
        if (trim($query) === '' && ! $this->hasActiveFilters($filters)) {
            return [
                'results' => [],
                'facets' => ['total' => 0, 'receipts' => 0, 'documents' => 0],
            ];
        }

        $results = collect();

        // Search receipts if not filtered out
        if (! isset($filters['type']) || $filters['type'] === 'all' || $filters['type'] === 'receipt') {
            $receipts = $this->searchReceipts($query, $filters);
            $results = $results->concat($receipts);
        }

        // Search documents if not filtered out
        if (! isset($filters['type']) || $filters['type'] === 'all' || $filters['type'] === 'document') {
            $documents = $this->searchDocuments($query, $filters);
            $results = $results->concat($documents);
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
            'facets' => $this->buildFacets($query, $filters),
        ];
    }

    protected function hasActiveFilters(array $filters): bool
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

    /**
     * Search receipts
     */
    protected function searchReceipts(string $query, array $filters): Collection
    {
        // For multi-word queries, we need to search each word and combine results
        // to ensure documents matching ANY word are found (OR behavior)
        $words = array_filter(array_unique(str_word_count($query, 1)));

        // If single word or empty, use standard search
        if (count($words) <= 1) {
            return $this->executeReceiptSearch($query, $filters);
        }

        // Multi-word search: search each word and track matches
        $resultsById = [];

        // Search for each word and track which words each document matches
        foreach ($words as $word) {
            $wordResults = $this->executeReceiptSearch($word, $filters);
            foreach ($wordResults as $result) {
                $id = $result['id'];
                if (!isset($resultsById[$id])) {
                    $result['_matchedWords'] = 1;
                    $result['_matchedWordsList'] = [$word];
                    $result['_maxScore'] = $result['_rankingScore'] ?? 0;
                    $resultsById[$id] = $result;
                } else {
                    // Document matches another word - increment count
                    $resultsById[$id]['_matchedWords']++;
                    $resultsById[$id]['_matchedWordsList'][] = $word;
                    // Keep the highest score from any individual word match
                    $currentScore = $result['_rankingScore'] ?? 0;
                    if ($currentScore > $resultsById[$id]['_maxScore']) {
                        $resultsById[$id]['_maxScore'] = $currentScore;
                    }
                }
            }
        }

        // Calculate final scores: more matched words = much higher score
        $allResults = collect($resultsById)->map(function ($result) use ($words) {
            $matchRatio = $result['_matchedWords'] / count($words);
            $result['_boostedScore'] = ($result['_matchedWords'] * 100) + ($result['_maxScore'] * 10);
            return $result;
        });

        // Sort by: 1) number of matched words (desc), 2) boosted score (desc)
        return $allResults->sortByDesc(function ($item) {
            return ($item['_matchedWords'] * 1000) + ($item['_boostedScore'] ?? 0);
        })->values();
    }

    /**
     * Execute a single receipt search query
     */
    protected function executeReceiptSearch(string $query, array $filters): Collection
    {
        $searchQuery = Receipt::search($query)
            ->options([
                'showRankingScore' => true,
            ])
            // Scope search results to current user
            ->where('user_id', auth()->id());

        // Apply date filters
        if (isset($filters['date_from'])) {
            $searchQuery->where('receipt_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $searchQuery->where('receipt_date', '<=', $filters['date_to']);
        }

        // Apply amount filters
        if (isset($filters['amount_min'])) {
            $searchQuery->where('total_amount', '>=', $filters['amount_min']);
        }
        if (isset($filters['amount_max'])) {
            $searchQuery->where('total_amount', '<=', $filters['amount_max']);
        }

        // Apply category filter
        if (isset($filters['category'])) {
            $searchQuery->where('receipt_category', $filters['category']);
        }

        // Apply vendor filter (supports string or array)
        if (isset($filters['vendor']) && is_string($filters['vendor']) && $filters['vendor'] !== '') {
            $searchQuery->where('vendors', $filters['vendor']);
        }
        if (isset($filters['vendors']) && is_array($filters['vendors']) && ! empty($filters['vendors'])) {
            // Apply AND semantics for multiple vendors
            foreach ($filters['vendors'] as $v) {
                if (is_string($v) && $v !== '') {
                    $searchQuery->where('vendors', $v);
                }
            }
        }

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['merchant', 'lineItems', 'file', 'tags']);
            })
            ->get();

        return $results->map(function ($receipt) {
            $metadata = method_exists($receipt, 'scoutMetadata') ? $receipt->scoutMetadata() : [];

            $description = $receipt->note ?: $receipt->summary ?: $this->buildReceiptDescription($receipt);

            return [
                'id' => $receipt->id,
                'type' => 'receipt',
                'title' => $receipt->merchant?->name ?? 'Unknown Merchant',
                'description' => $description,
                'filename' => $receipt->file?->original_filename ?? $receipt->file?->fileName,
                'url' => route('receipts.show', $receipt->id),
                'date' => $this->formatDate($receipt->receipt_date),
                'total' => $receipt->total_amount ? number_format($receipt->total_amount, 2).' '.$receipt->currency : null,
                'category' => $receipt->receipt_category,
                'tags' => $receipt->tags ? $receipt->tags->pluck('name')->all() : [],
                'items' => $receipt->lineItems ? $receipt->lineItems->take(3)->map(function ($item) use ($receipt) {
                    return [
                        'description' => $item->text ?? '',
                        'quantity' => $item->qty ?? 0,
                        'price' => number_format($item->price ?? 0, 2).' '.($receipt->currency ?? ''),
                    ];
                })->all() : [],
                'file' => $receipt->file ? [
                    'id' => $receipt->file->id,
                    'guid' => $receipt->file->guid,
                    'filename' => $receipt->file->original_filename ?? $receipt->file->fileName,
                    'extension' => $receipt->file->fileExtension,
                    'has_image_preview' => (bool) $receipt->file->has_image_preview,
                    'has_converted_pdf' => ! empty($receipt->file->s3_converted_path),
                    'url' => route('receipts.showImage', $receipt->id),
                    'pdfUrl' => $receipt->file->guid && $receipt->file->fileExtension === 'pdf' ? route('receipts.showPdf', $receipt->id) : null,
                    'previewUrl' => $receipt->file->has_image_preview ? route('receipts.showImage', $receipt->id) : null,
                ] : null,
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    /**
     * Search documents
     */
    protected function searchDocuments(string $query, array $filters): Collection
    {
        // For multi-word queries, search each word and combine results (OR behavior)
        $words = array_filter(array_unique(str_word_count($query, 1)));

        // If single word or empty, use standard search
        if (count($words) <= 1) {
            return $this->executeDocumentSearch($query, $filters);
        }

        // Multi-word search: search each word and track matches
        $resultsById = [];

        // Search for each word and track which words each document matches
        foreach ($words as $word) {
            $wordResults = $this->executeDocumentSearch($word, $filters);
            foreach ($wordResults as $result) {
                $id = $result['id'];
                if (!isset($resultsById[$id])) {
                    $result['_matchedWords'] = 1;
                    $result['_matchedWordsList'] = [$word];
                    $result['_maxScore'] = $result['_rankingScore'] ?? 0;
                    $resultsById[$id] = $result;
                } else {
                    // Document matches another word - increment count
                    $resultsById[$id]['_matchedWords']++;
                    $resultsById[$id]['_matchedWordsList'][] = $word;
                    // Keep the highest score from any individual word match
                    $currentScore = $result['_rankingScore'] ?? 0;
                    if ($currentScore > $resultsById[$id]['_maxScore']) {
                        $resultsById[$id]['_maxScore'] = $currentScore;
                    }
                }
            }
        }

        // Calculate final scores: more matched words = much higher score
        $allResults = collect($resultsById)->map(function ($result) use ($words) {
            $matchRatio = $result['_matchedWords'] / count($words);
            $result['_boostedScore'] = ($result['_matchedWords'] * 100) + ($result['_maxScore'] * 10);
            return $result;
        });

        // Sort by: 1) number of matched words (desc), 2) boosted score (desc)
        return $allResults->sortByDesc(function ($item) {
            return ($item['_matchedWords'] * 1000) + ($item['_boostedScore'] ?? 0);
        })->values();
    }

    /**
     * Execute a single document search query
     */
    protected function executeDocumentSearch(string $query, array $filters): Collection
    {
        $searchQuery = Document::search($query)
            ->options([
                'showRankingScore' => true,
            ])
            // Scope search results to current user
            ->where('user_id', auth()->id());

        // Apply date filters
        if (isset($filters['date_from'])) {
            $searchQuery->where('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $searchQuery->where('created_at', '<=', $filters['date_to']);
        }

        // Apply document type filter
        if (isset($filters['document_type'])) {
            $searchQuery->where('document_type', $filters['document_type']);
        }

        // Apply tag filters
        if (isset($filters['tags']) && is_array($filters['tags'])) {
            // Tag filtering is handled in the query callback
        }

        $results = $searchQuery
            ->query(function ($builder) use ($filters) {
                $builder->with(['tags', 'file']);

                // Apply tag filtering at the database level
                if (isset($filters['tags']) && is_array($filters['tags'])) {
                    $builder->whereHas('tags', function ($q) use ($filters) {
                        $q->whereIn('name', $filters['tags']);
                    });
                }
            })
            ->get();

        return $results->map(function ($document) {
            $metadata = method_exists($document, 'scoutMetadata') ? $document->scoutMetadata() : [];

            $description = $document->note ?: $document->summary ?: $document->description;

            if (! $description) {
                $text = $document->extracted_text;
                if (is_array($text)) {
                    $text = implode(' ', $text);
                }
                if (is_string($text)) {
                    $description = $this->truncateText($text, 150);
                }
            }

            // Build file info similar to DocumentTransformer
            $fileInfo = null;
            if ($document->file && $document->file->guid) {
                $extension = $document->file->fileExtension ?? 'pdf';
                $typeFolder = 'documents';
                $hasConvertedPdf = ! empty($document->file->s3_converted_path);

                $pdfUrl = null;
                if ($hasConvertedPdf || strtolower($extension) === 'pdf') {
                    $pdfUrl = route('documents.serve', [
                        'guid' => $document->file->guid,
                        'type' => $typeFolder,
                        'extension' => 'pdf',
                        'variant' => $hasConvertedPdf ? 'archive' : 'original',
                    ]);
                }

                $previewUrl = null;
                if ($document->file->has_image_preview && $document->file->s3_image_path) {
                    $previewUrl = route('documents.serve', [
                        'guid' => $document->file->guid,
                        'type' => 'preview',
                        'extension' => 'jpg',
                    ]);
                }

                $fileInfo = [
                    'id' => $document->file->id,
                    'guid' => $document->file->guid,
                    'filename' => $document->file->original_filename ?? $document->file->fileName,
                    'extension' => $extension,
                    'has_image_preview' => (bool) $document->file->has_image_preview,
                    'has_converted_pdf' => $hasConvertedPdf,
                    'url' => route('documents.serve', [
                        'guid' => $document->file->guid,
                        'type' => $typeFolder,
                        'extension' => $extension,
                    ]),
                    'pdfUrl' => $pdfUrl,
                    'previewUrl' => $previewUrl,
                ];
            }

            return [
                'id' => $document->id,
                'type' => 'document',
                'title' => $document->title ?? 'Untitled Document',
                'description' => $description,
                'filename' => $document->file?->original_filename ?? $document->file?->fileName,
                'url' => route('documents.show', $document->id),
                'date' => $this->formatDate($document->document_date ?? $document->created_at),
                'document_type' => $document->document_type,
                'category' => $document->category?->name ?? null,
                'tags' => $document->tags ? $document->tags->pluck('name')->all() : [],
                'entities' => $document->entities ?? [],
                'file' => $fileInfo,
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    /**
     * Build facets for search results
     */
    protected function buildFacets(string $query, array $filters): array
    {
        // Simple facet counts
        if (trim($query) === '' && ! $this->hasActiveFilters($filters)) {
            return [
                'total' => 0,
                'receipts' => 0,
                'documents' => 0,
            ];
        }

        // Get type counts with filters applied
        $receiptQuery = Receipt::search($query)->where('user_id', auth()->id());
        $documentQuery = Document::search($query)->where('user_id', auth()->id());

        // Apply filters to facet queries
        if (isset($filters['date_from'])) {
            $receiptQuery->where('receipt_date', '>=', $filters['date_from']);
            $documentQuery->where('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $receiptQuery->where('receipt_date', '<=', $filters['date_to']);
            $documentQuery->where('created_at', '<=', $filters['date_to']);
        }

        try {
            $receiptCount = $receiptQuery->raw()['estimatedTotalHits'] ?? 0;
            $documentCount = $documentQuery->raw()['estimatedTotalHits'] ?? 0;
        } catch (\Exception $e) {
            $receiptCount = 0;
            $documentCount = 0;
        }

        return [
            'total' => $receiptCount + $documentCount,
            'receipts' => $receiptCount,
            'documents' => $documentCount,
        ];
    }

    /**
     * Build receipt description
     */
    protected function buildReceiptDescription(Receipt $receipt): string
    {
        $parts = [];

        if ($receipt->merchant) {
            $parts[] = $receipt->merchant->name;
        }

        if ($receipt->total_amount) {
            $parts[] = number_format($receipt->total_amount, 2).' '.$receipt->currency;
        }

        if ($receipt->receipt_date) {
            $parts[] = $this->formatDate($receipt->receipt_date);
        }

        if ($receipt->receipt_category) {
            $parts[] = $receipt->receipt_category;
        }

        return implode(' - ', $parts);
    }

    /**
     * Format date consistently
     */
    protected function formatDate($date): ?string
    {
        if (! $date) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }

        return Carbon::parse($date)->format('Y-m-d');
    }

    /**
     * Truncate text with ellipsis
     */
    protected function truncateText(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3).'...';
    }
}
