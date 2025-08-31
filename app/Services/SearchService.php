<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Receipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * Search across all content types
     */
    public function search(string $query, array $filters = []): array
    {
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

    /**
     * Search receipts
     */
    protected function searchReceipts(string $query, array $filters): Collection
    {
        $searchQuery = Receipt::search($query);

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

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['merchant', 'lineItems']);
            })
            ->get();

        return $results->map(function ($receipt) {
            $description = $this->buildReceiptDescription($receipt);

            return [
                'id' => $receipt->id,
                'type' => 'receipt',
                'title' => $receipt->merchant?->name ?? 'Unknown Merchant',
                'description' => $description,
                'url' => route('receipts.show', $receipt->id),
                'date' => $this->formatDate($receipt->receipt_date),
                'total' => $receipt->total_amount ? number_format($receipt->total_amount, 2).' '.$receipt->currency : null,
                'category' => $receipt->receipt_category,
                'tags' => [],
                'items' => $receipt->lineItems->take(3)->map(function ($item) {
                    return $item->text;
                })->join(', '),
                'score' => $receipt->_rankingScore ?? null,
            ];
        });
    }

    /**
     * Search documents
     */
    protected function searchDocuments(string $query, array $filters): Collection
    {
        $searchQuery = Document::search($query);

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
            // This would require a more complex query with tag relationships
            // For now, we'll handle it in the query callback
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
            return [
                'id' => $document->id,
                'type' => 'document',
                'title' => $document->title,
                'description' => $document->description ?? $this->truncateText($document->extracted_text, 150),
                'url' => route('documents.show', $document->id),
                'date' => $this->formatDate($document->created_at),
                'document_type' => $document->document_type,
                'category' => $document->category,
                'tags' => $document->tags->pluck('name')->all(),
                'entities' => $document->entities ?? [],
                'score' => $document->_rankingScore ?? null,
            ];
        });
    }

    /**
     * Build facets for search results
     */
    protected function buildFacets(string $query, array $filters): array
    {
        $facets = [
            'types' => [],
            'categories' => [],
            'tags' => [],
            'date_ranges' => [],
        ];

        // Get type counts
        $receiptCount = Receipt::search($query)->raw()['estimatedTotalHits'] ?? 0;
        $documentCount = Document::search($query)->raw()['estimatedTotalHits'] ?? 0;

        $facets['types'] = [
            ['value' => 'receipt', 'count' => $receiptCount, 'label' => 'Receipts'],
            ['value' => 'document', 'count' => $documentCount, 'label' => 'Documents'],
        ];

        // Get categories (would need aggregation queries)
        // This is simplified - in production you'd want proper faceted search

        return $facets;
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
