<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Voucher;
use App\Models\Warranty;
use Closure;
use Illuminate\Support\Collection;

/**
 * Builds and executes Meilisearch queries with filters and multi-word OR logic.
 */
class SearchQueryBuilder
{
    protected SearchResultFormatter $formatter;

    public function __construct(SearchResultFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function searchReceipts(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeReceiptSearch($q, $filters));
    }

    public function searchDocuments(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeDocumentSearch($q, $filters));
    }

    public function searchInvoices(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeInvoiceSearch($q, $filters));
    }

    public function searchContracts(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeContractSearch($q, $filters));
    }

    public function searchVouchers(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeVoucherSearch($q, $filters));
    }

    public function searchWarranties(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeWarrantySearch($q, $filters));
    }

    public function searchReturnPolicies(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeReturnPolicySearch($q, $filters));
    }

    public function searchBankStatements(string $query, array $filters): Collection
    {
        return $this->multiWordSearch($query, fn (string $q) => $this->executeBankStatementSearch($q, $filters));
    }

    /**
     * Multi-word OR search: search each word separately and boost results matching more words.
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

        $allResults = collect($resultsById)->map(function ($result) {
            $result['_boostedScore'] = ($result['_matchedWords'] * 100) + ($result['_maxScore'] * 10);

            return $result;
        });

        return $allResults->sortByDesc(function ($item) {
            return ($item['_matchedWords'] * 1000) + ($item['_boostedScore'] ?? 0);
        })->values();
    }

    protected function executeReceiptSearch(string $query, array $filters): Collection
    {
        $searchQuery = Receipt::search($query)
            ->options(['showRankingScore' => true])
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

        return $this->formatter->formatReceipts($results);
    }

    protected function executeDocumentSearch(string $query, array $filters): Collection
    {
        $searchQuery = Document::search($query)
            ->options(['showRankingScore' => true])
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

        return $this->formatter->formatDocuments($results);
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

        return $this->formatter->formatInvoices($results);
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

        return $this->formatter->formatContracts($results);
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

        return $this->formatter->formatVouchers($results);
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

        return $this->formatter->formatWarranties($results);
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

        return $this->formatter->formatReturnPolicies($results);
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
                $builder->with(['file', 'tags']);

                if (isset($filters['collection_id']) && $filters['collection_id']) {
                    $builder->whereHas('file.collections', function ($q) use ($filters) {
                        $q->where('collections.id', $filters['collection_id']);
                    });
                }
            })
            ->get();

        return $this->formatter->formatBankStatements($results);
    }
}
