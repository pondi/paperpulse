<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Voucher;
use App\Models\Warranty;
use Closure;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SearchService
{
    private const EMPTY_FACETS = [
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

    /**
     * Search across all content types
     */
    public function search(string $query, array $filters = []): array
    {
        if (trim($query) === '' && ! $this->hasActiveFilters($filters)) {
            return [
                'results' => [],
                'facets' => self::EMPTY_FACETS,
            ];
        }

        $type = $filters['type'] ?? 'all';
        $results = collect();

        $typeSearchMap = [
            'receipt' => fn () => $this->searchReceipts($query, $filters),
            'document' => fn () => $this->searchDocuments($query, $filters),
            'invoice' => fn () => $this->searchInvoices($query, $filters),
            'contract' => fn () => $this->searchContracts($query, $filters),
            'voucher' => fn () => $this->searchVouchers($query, $filters),
            'warranty' => fn () => $this->searchWarranties($query, $filters),
            'return_policy' => fn () => $this->searchReturnPolicies($query, $filters),
            'bank_statement' => fn () => $this->searchBankStatements($query, $filters),
        ];

        foreach ($typeSearchMap as $typeKey => $searchFn) {
            if ($type === 'all' || $type === $typeKey) {
                try {
                    $results = $results->concat($searchFn());
                } catch (Exception $e) {
                    // Skip this type if its index is not configured
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
     * Multi-word OR search: search each word separately and boost results matching more words
     */
    protected function multiWordSearch(string $query, Closure $executeSearch): Collection
    {
        $words = array_filter(array_unique(str_word_count($query, 1)));

        if (count($words) <= 1) {
            return $executeSearch($query);
        }

        $resultsById = [];

        foreach ($words as $word) {
            $wordResults = $executeSearch($word);
            foreach ($wordResults as $result) {
                $id = $result['id'];
                if (! isset($resultsById[$id])) {
                    $result['_matchedWords'] = 1;
                    $result['_matchedWordsList'] = [$word];
                    $result['_maxScore'] = $result['_rankingScore'] ?? 0;
                    $resultsById[$id] = $result;
                } else {
                    $resultsById[$id]['_matchedWords']++;
                    $resultsById[$id]['_matchedWordsList'][] = $word;
                    $currentScore = $result['_rankingScore'] ?? 0;
                    if ($currentScore > $resultsById[$id]['_maxScore']) {
                        $resultsById[$id]['_maxScore'] = $currentScore;
                    }
                }
            }
        }

        $allResults = collect($resultsById)->map(function ($result) use ($words) {
            $matchRatio = $result['_matchedWords'] / count($words);
            $result['_boostedScore'] = ($result['_matchedWords'] * 100) + ($result['_maxScore'] * 10);

            return $result;
        });

        return $allResults->sortByDesc(function ($item) {
            return ($item['_matchedWords'] * 1000) + ($item['_boostedScore'] ?? 0);
        })->values();
    }

    /**
     * Build file info array for an entity's file relationship
     */
    protected function buildEntityFileInfo($file): ?array
    {
        if (! $file || ! $file->guid) {
            return null;
        }

        $extension = $file->fileExtension ?? 'pdf';
        $hasArchivePdf = ! empty($file->s3_archive_path);

        $pdfUrl = null;
        if ($hasArchivePdf || strtolower($extension) === 'pdf') {
            $pdfUrl = route('documents.serve', [
                'guid' => $file->guid,
                'type' => 'documents',
                'extension' => 'pdf',
                'variant' => $hasArchivePdf ? 'archive' : 'original',
            ]);
        }

        $previewUrl = null;
        if ($file->has_image_preview && $file->s3_image_path) {
            $previewUrl = route('documents.serve', [
                'guid' => $file->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        return [
            'id' => $file->id,
            'guid' => $file->guid,
            'filename' => $file->original_filename ?? $file->fileName,
            'extension' => $extension,
            'has_image_preview' => (bool) $file->has_image_preview,
            'has_archive_pdf' => $hasArchivePdf,
            'url' => route('documents.serve', [
                'guid' => $file->guid,
                'type' => 'documents',
                'extension' => $extension,
            ]),
            'pdfUrl' => $pdfUrl,
            'previewUrl' => $previewUrl,
        ];
    }

    // ─── Receipt search ──────────────────────────────────────────────

    protected function searchReceipts(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeReceiptSearch($q, $filters));
    }

    protected function executeReceiptSearch(string $query, array $filters): Collection
    {
        $searchQuery = Receipt::search($query)
            ->options([
                'showRankingScore' => true,
            ])
            ->where('user_id', auth()->id());

        if (isset($filters['date_from'])) {
            $searchQuery->where('receipt_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $searchQuery->where('receipt_date', '<=', $filters['date_to']);
        }
        if (isset($filters['amount_min'])) {
            $searchQuery->where('total_amount', '>=', $filters['amount_min']);
        }
        if (isset($filters['amount_max'])) {
            $searchQuery->where('total_amount', '<=', $filters['amount_max']);
        }
        if (isset($filters['category'])) {
            $searchQuery->where('receipt_category', $filters['category']);
        }
        if (isset($filters['vendor']) && is_string($filters['vendor']) && $filters['vendor'] !== '') {
            $searchQuery->where('vendors', $filters['vendor']);
        }
        if (isset($filters['vendors']) && is_array($filters['vendors']) && ! empty($filters['vendors'])) {
            foreach ($filters['vendors'] as $v) {
                if (is_string($v) && $v !== '') {
                    $searchQuery->where('vendors', $v);
                }
            }
        }

        $results = $searchQuery
            ->query(function ($builder) use ($filters) {
                $builder->with(['merchant', 'lineItems', 'file', 'tags']);

                if (isset($filters['collection_id']) && $filters['collection_id']) {
                    $builder->whereHas('file.collections', function ($q) use ($filters) {
                        $q->where('collections.id', $filters['collection_id']);
                    });
                }
            })
            ->get();

        return $results->map(function ($receipt) {
            $metadata = method_exists($receipt, 'scoutMetadata') ? $receipt->scoutMetadata() : [];

            $description = $receipt->note ?: $receipt->summary ?: $this->buildReceiptDescription($receipt);

            return [
                'id' => $receipt->id,
                'file_id' => $receipt->file_id,
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
                    'has_archive_pdf' => ! empty($receipt->file->s3_archive_path),
                    'url' => route('receipts.showImage', $receipt->id),
                    'pdfUrl' => $receipt->file->guid && $receipt->file->fileExtension === 'pdf' ? route('receipts.showPdf', $receipt->id) : null,
                    'previewUrl' => $receipt->file->has_image_preview ? route('receipts.showImage', $receipt->id) : null,
                ] : null,
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    // ─── Document search ─────────────────────────────────────────────

    protected function searchDocuments(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeDocumentSearch($q, $filters));
    }

    protected function executeDocumentSearch(string $query, array $filters): Collection
    {
        $searchQuery = Document::search($query)
            ->options([
                'showRankingScore' => true,
            ])
            ->where('user_id', auth()->id());

        if (isset($filters['date_from'])) {
            $searchQuery->where('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $searchQuery->where('created_at', '<=', $filters['date_to']);
        }
        if (isset($filters['document_type'])) {
            $searchQuery->where('document_type', $filters['document_type']);
        }
        if (isset($filters['tags']) && is_array($filters['tags'])) {
            // Tag filtering is handled in the query callback
        }

        $results = $searchQuery
            ->query(function ($builder) use ($filters) {
                $builder->with(['tags', 'file']);

                if (isset($filters['tags']) && is_array($filters['tags'])) {
                    $builder->whereHas('tags', function ($q) use ($filters) {
                        $q->whereIn('name', $filters['tags']);
                    });
                }

                if (isset($filters['collection_id']) && $filters['collection_id']) {
                    $builder->whereHas('file.collections', function ($q) use ($filters) {
                        $q->where('collections.id', $filters['collection_id']);
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

            $fileInfo = null;
            if ($document->file && $document->file->guid) {
                $extension = $document->file->fileExtension ?? 'pdf';
                $typeFolder = 'documents';
                $hasArchivePdf = ! empty($document->file->s3_archive_path);

                $pdfUrl = null;
                if ($hasArchivePdf || strtolower($extension) === 'pdf') {
                    $pdfUrl = route('documents.serve', [
                        'guid' => $document->file->guid,
                        'type' => $typeFolder,
                        'extension' => 'pdf',
                        'variant' => $hasArchivePdf ? 'archive' : 'original',
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
                    'has_archive_pdf' => $hasArchivePdf,
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
                'file_id' => $document->file_id,
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

    // ─── Invoice search ──────────────────────────────────────────────

    protected function searchInvoices(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeInvoiceSearch($q, $filters));
    }

    protected function executeInvoiceSearch(string $query, array $filters): Collection
    {
        $searchQuery = Invoice::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        if (isset($filters['date_from'])) {
            $searchQuery->where('invoice_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $searchQuery->where('invoice_date', '<=', $filters['date_to']);
        }
        if (isset($filters['amount_min'])) {
            $searchQuery->where('total_amount', '>=', $filters['amount_min']);
        }
        if (isset($filters['amount_max'])) {
            $searchQuery->where('total_amount', '<=', $filters['amount_max']);
        }

        $results = $searchQuery
            ->query(function ($builder) use ($filters) {
                $builder->with(['merchant', 'lineItems', 'file', 'tags']);

                if (isset($filters['collection_id']) && $filters['collection_id']) {
                    $builder->whereHas('file.collections', function ($q) use ($filters) {
                        $q->where('collections.id', $filters['collection_id']);
                    });
                }
            })
            ->get();

        return $results->map(function ($invoice) {
            $metadata = method_exists($invoice, 'scoutMetadata') ? $invoice->scoutMetadata() : [];
            $title = $invoice->from_name ?: ('Invoice #'.$invoice->invoice_number);
            $description = $invoice->notes ?: ($invoice->to_name ? 'To: '.$invoice->to_name : null);

            return [
                'id' => $invoice->id,
                'file_id' => $invoice->file?->id,
                'type' => 'invoice',
                'title' => $title,
                'description' => $description,
                'filename' => $invoice->file?->original_filename ?? $invoice->file?->fileName,
                'url' => route('invoices.show', $invoice->id),
                'date' => $this->formatDate($invoice->invoice_date),
                'total' => $invoice->total_amount ? number_format($invoice->total_amount, 2).' '.($invoice->currency ?? '') : null,
                'payment_status' => $invoice->payment_status,
                'invoice_number' => $invoice->invoice_number,
                'tags' => $invoice->tags ? $invoice->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($invoice->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    // ─── Contract search ─────────────────────────────────────────────

    protected function searchContracts(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeContractSearch($q, $filters));
    }

    protected function executeContractSearch(string $query, array $filters): Collection
    {
        $searchQuery = Contract::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        if (isset($filters['date_from'])) {
            $searchQuery->where('effective_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $searchQuery->where('effective_date', '<=', $filters['date_to']);
        }

        $results = $searchQuery
            ->query(function ($builder) use ($filters) {
                $builder->with(['file', 'tags']);

                if (isset($filters['collection_id']) && $filters['collection_id']) {
                    $builder->whereHas('file.collections', function ($q) use ($filters) {
                        $q->where('collections.id', $filters['collection_id']);
                    });
                }
            })
            ->get();

        return $results->map(function ($contract) {
            $metadata = method_exists($contract, 'scoutMetadata') ? $contract->scoutMetadata() : [];

            return [
                'id' => $contract->id,
                'file_id' => $contract->file?->id,
                'type' => 'contract',
                'title' => $contract->contract_title ?: ('Contract #'.$contract->contract_number),
                'description' => $contract->summary,
                'filename' => $contract->file?->original_filename ?? $contract->file?->fileName,
                'url' => route('contracts.show', $contract->id),
                'date' => $this->formatDate($contract->effective_date),
                'contract_type' => $contract->contract_type,
                'status' => $contract->status,
                'total' => $contract->contract_value ? number_format($contract->contract_value, 2).' '.($contract->currency ?? '') : null,
                'tags' => $contract->tags ? $contract->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($contract->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    // ─── Voucher search ──────────────────────────────────────────────

    protected function searchVouchers(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeVoucherSearch($q, $filters));
    }

    protected function executeVoucherSearch(string $query, array $filters): Collection
    {
        $searchQuery = Voucher::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        if (isset($filters['date_from'])) {
            $searchQuery->where('expiry_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $searchQuery->where('expiry_date', '<=', $filters['date_to']);
        }

        $results = $searchQuery
            ->query(function ($builder) use ($filters) {
                $builder->with(['merchant', 'file', 'tags']);

                if (isset($filters['collection_id']) && $filters['collection_id']) {
                    $builder->whereHas('file.collections', function ($q) use ($filters) {
                        $q->where('collections.id', $filters['collection_id']);
                    });
                }
            })
            ->get();

        return $results->map(function ($voucher) {
            $metadata = method_exists($voucher, 'scoutMetadata') ? $voucher->scoutMetadata() : [];
            $title = $voucher->code ?: ($voucher->merchant?->name ? $voucher->merchant->name.' Voucher' : 'Voucher');

            return [
                'id' => $voucher->id,
                'file_id' => $voucher->file?->id,
                'type' => 'voucher',
                'title' => $title,
                'description' => $voucher->terms_and_conditions ? $this->truncateText($voucher->terms_and_conditions, 150) : null,
                'filename' => $voucher->file?->original_filename ?? $voucher->file?->fileName,
                'url' => route('vouchers.show', $voucher->id),
                'date' => $this->formatDate($voucher->expiry_date),
                'total' => $voucher->current_value ? number_format($voucher->current_value, 2).' '.($voucher->currency ?? '') : null,
                'voucher_type' => $voucher->voucher_type,
                'is_redeemed' => $voucher->is_redeemed,
                'tags' => $voucher->tags ? $voucher->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($voucher->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    // ─── Warranty search ─────────────────────────────────────────────

    protected function searchWarranties(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeWarrantySearch($q, $filters));
    }

    protected function executeWarrantySearch(string $query, array $filters): Collection
    {
        $searchQuery = Warranty::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        if (isset($filters['date_from'])) {
            $searchQuery->where('warranty_end_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $searchQuery->where('warranty_end_date', '<=', $filters['date_to']);
        }

        $results = $searchQuery
            ->query(function ($builder) use ($filters) {
                $builder->with(['file', 'tags']);

                if (isset($filters['collection_id']) && $filters['collection_id']) {
                    $builder->whereHas('file.collections', function ($q) use ($filters) {
                        $q->where('collections.id', $filters['collection_id']);
                    });
                }
            })
            ->get();

        return $results->map(function ($warranty) {
            $metadata = method_exists($warranty, 'scoutMetadata') ? $warranty->scoutMetadata() : [];

            return [
                'id' => $warranty->id,
                'file_id' => $warranty->file?->id,
                'type' => 'warranty',
                'title' => $warranty->product_name ?: 'Warranty',
                'description' => $warranty->coverage_description ? $this->truncateText($warranty->coverage_description, 150) : ($warranty->manufacturer ? 'Manufacturer: '.$warranty->manufacturer : null),
                'filename' => $warranty->file?->original_filename ?? $warranty->file?->fileName,
                'url' => $warranty->file ? route('documents.show', $warranty->file->id) : null,
                'date' => $this->formatDate($warranty->warranty_end_date),
                'warranty_type' => $warranty->warranty_type,
                'manufacturer' => $warranty->manufacturer,
                'tags' => $warranty->tags ? $warranty->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($warranty->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    // ─── Return Policy search ────────────────────────────────────────

    protected function searchReturnPolicies(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeReturnPolicySearch($q, $filters));
    }

    protected function executeReturnPolicySearch(string $query, array $filters): Collection
    {
        $searchQuery = ReturnPolicy::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        if (isset($filters['date_from'])) {
            $searchQuery->where('return_deadline', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $searchQuery->where('return_deadline', '<=', $filters['date_to']);
        }

        $results = $searchQuery
            ->query(function ($builder) use ($filters) {
                $builder->with(['merchant', 'file', 'tags']);

                if (isset($filters['collection_id']) && $filters['collection_id']) {
                    $builder->whereHas('file.collections', function ($q) use ($filters) {
                        $q->where('collections.id', $filters['collection_id']);
                    });
                }
            })
            ->get();

        return $results->map(function ($policy) {
            $metadata = method_exists($policy, 'scoutMetadata') ? $policy->scoutMetadata() : [];
            $title = $policy->merchant?->name ? $policy->merchant->name.' Return Policy' : 'Return Policy';

            return [
                'id' => $policy->id,
                'file_id' => $policy->file?->id,
                'type' => 'return_policy',
                'title' => $title,
                'description' => $policy->conditions ? $this->truncateText($policy->conditions, 150) : ($policy->refund_method ? 'Refund: '.$policy->refund_method : null),
                'filename' => $policy->file?->original_filename ?? $policy->file?->fileName,
                'url' => $policy->file ? route('documents.show', $policy->file->id) : null,
                'date' => $this->formatDate($policy->return_deadline),
                'is_final_sale' => $policy->is_final_sale,
                'refund_method' => $policy->refund_method,
                'tags' => $policy->tags ? $policy->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($policy->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    // ─── Bank Statement search ───────────────────────────────────────

    protected function searchBankStatements(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeBankStatementSearch($q, $filters));
    }

    protected function executeBankStatementSearch(string $query, array $filters): Collection
    {
        $searchQuery = BankStatement::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        if (isset($filters['date_from'])) {
            $searchQuery->where('statement_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $searchQuery->where('statement_date', '<=', $filters['date_to']);
        }

        $results = $searchQuery
            ->query(function ($builder) use ($filters) {
                $builder->with(['transactions', 'file', 'tags']);

                if (isset($filters['collection_id']) && $filters['collection_id']) {
                    $builder->whereHas('file.collections', function ($q) use ($filters) {
                        $q->where('collections.id', $filters['collection_id']);
                    });
                }
            })
            ->get();

        return $results->map(function ($statement) {
            $metadata = method_exists($statement, 'scoutMetadata') ? $statement->scoutMetadata() : [];
            $title = $statement->bank_name ?: 'Bank Statement';
            $description = $statement->account_holder_name ? 'Account: '.$statement->account_holder_name : null;

            return [
                'id' => $statement->id,
                'file_id' => $statement->file?->id,
                'type' => 'bank_statement',
                'title' => $title,
                'description' => $description,
                'filename' => $statement->file?->original_filename ?? $statement->file?->fileName,
                'url' => $statement->file ? route('documents.show', $statement->file->id) : null,
                'date' => $this->formatDate($statement->statement_date),
                'total' => $statement->closing_balance ? number_format($statement->closing_balance, 2).' '.($statement->currency ?? '') : null,
                'transaction_count' => $statement->transaction_count,
                'tags' => $statement->tags ? $statement->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($statement->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    // ─── Facets ──────────────────────────────────────────────────────

    protected function buildFacets(string $query, array $filters): array
    {
        if (trim($query) === '' && ! $this->hasActiveFilters($filters)) {
            return self::EMPTY_FACETS;
        }

        $userId = auth()->id();

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

        // Apply date filters with type-appropriate date fields
        $dateFieldMap = [
            'receipts' => 'receipt_date',
            'documents' => 'created_at',
            'invoices' => 'invoice_date',
            'contracts' => 'effective_date',
            'vouchers' => 'expiry_date',
            'warranties' => 'warranty_end_date',
            'return_policies' => 'return_deadline',
            'bank_statements' => 'statement_date',
        ];

        foreach ($queries as $type => $searchQuery) {
            $dateField = $dateFieldMap[$type];
            if (isset($filters['date_from'])) {
                $searchQuery->where($dateField, '>=', $filters['date_from']);
            }
            if (isset($filters['date_to'])) {
                $searchQuery->where($dateField, '<=', $filters['date_to']);
            }
        }

        $counts = [];
        $total = 0;

        foreach ($queries as $type => $searchQuery) {
            try {
                $count = $searchQuery->raw()['estimatedTotalHits'] ?? 0;
            } catch (Exception $e) {
                $count = 0;
            }
            $counts[$type] = $count;
            $total += $count;
        }

        return ['total' => $total, ...$counts];
    }

    // ─── Helpers ─────────────────────────────────────────────────────

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

    protected function truncateText(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3).'...';
    }
}
