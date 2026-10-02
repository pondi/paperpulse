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

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['merchant', 'lineItems', 'file', 'tags']);
            })
            ->get();

        return $this->formatter->formatReceipts($results);
    }

    protected function executeDocumentSearch(string $query, array $filters): Collection
    {
        $searchQuery = Document::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['tags', 'file', 'category']);
            })
            ->get();

        return $this->formatter->formatDocuments($results);
    }

    protected function executeInvoiceSearch(string $query, array $filters): Collection
    {
        $searchQuery = Invoice::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['merchant', 'lineItems', 'file', 'tags']);
            })
            ->get();

        return $this->formatter->formatInvoices($results);
    }

    protected function executeContractSearch(string $query, array $filters): Collection
    {
        $searchQuery = Contract::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['file', 'tags']);
            })
            ->get();

        return $this->formatter->formatContracts($results);
    }

    protected function executeVoucherSearch(string $query, array $filters): Collection
    {
        $searchQuery = Voucher::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['merchant', 'file', 'tags', 'user.preferences']);
            })
            ->get();

        return $this->formatter->formatVouchers($results);
    }

    protected function executeWarrantySearch(string $query, array $filters): Collection
    {
        $searchQuery = Warranty::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['file', 'tags']);
            })
            ->get();

        return $this->formatter->formatWarranties($results);
    }

    protected function executeReturnPolicySearch(string $query, array $filters): Collection
    {
        $searchQuery = ReturnPolicy::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['merchant', 'file', 'tags']);
            })
            ->get();

        return $this->formatter->formatReturnPolicies($results);
    }

    protected function executeBankStatementSearch(string $query, array $filters): Collection
    {
        $searchQuery = BankStatement::search($query)
            ->options(['showRankingScore' => true])
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['file', 'tags']);
            })
            ->get();

        return $this->formatter->formatBankStatements($results);
    }
}
