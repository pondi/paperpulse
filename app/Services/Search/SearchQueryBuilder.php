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

/**
 * Builds bounded engine searches while preserving the original query.
 */
class SearchQueryBuilder
{
    protected SearchResultFormatter $formatter;

    public function __construct(SearchResultFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function searchReceipts(string $query, array $filters): array
    {
        return $this->executeReceiptSearch($query, $filters);
    }

    public function searchDocuments(string $query, array $filters): array
    {
        return $this->executeDocumentSearch($query, $filters);
    }

    public function searchInvoices(string $query, array $filters): array
    {
        return $this->executeInvoiceSearch($query, $filters);
    }

    public function searchContracts(string $query, array $filters): array
    {
        return $this->executeContractSearch($query, $filters);
    }

    public function searchVouchers(string $query, array $filters): array
    {
        return $this->executeVoucherSearch($query, $filters);
    }

    public function searchWarranties(string $query, array $filters): array
    {
        return $this->executeWarrantySearch($query, $filters);
    }

    public function searchReturnPolicies(string $query, array $filters): array
    {
        return $this->executeReturnPolicySearch($query, $filters);
    }

    public function searchBankStatements(string $query, array $filters): array
    {
        return $this->executeBankStatementSearch($query, $filters);
    }

    protected function executeReceiptSearch(string $query, array $filters): array
    {
        $searchQuery = Receipt::search($query)
            ->options(['showRankingScore' => true])
            ->orderBy('id')
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['merchant', 'lineItems', 'file', 'tags']);
            })
            ->paginate(($filters['page'] ?? 1) * ($filters['limit'] ?? 20), 'page', 1);

        return ['results' => $this->formatter->formatReceipts($results->getCollection()), 'total' => $results->total()];
    }

    protected function executeDocumentSearch(string $query, array $filters): array
    {
        $searchQuery = Document::search($query)
            ->options(['showRankingScore' => true])
            ->orderBy('id')
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['tags', 'file', 'category']);
            })
            ->paginate(($filters['page'] ?? 1) * ($filters['limit'] ?? 20), 'page', 1);

        return ['results' => $this->formatter->formatDocuments($results->getCollection()), 'total' => $results->total()];
    }

    protected function executeInvoiceSearch(string $query, array $filters): array
    {
        $searchQuery = Invoice::search($query)
            ->options(['showRankingScore' => true])
            ->orderBy('id')
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['merchant', 'lineItems', 'file', 'tags']);
            })
            ->paginate(($filters['page'] ?? 1) * ($filters['limit'] ?? 20), 'page', 1);

        return ['results' => $this->formatter->formatInvoices($results->getCollection()), 'total' => $results->total()];
    }

    protected function executeContractSearch(string $query, array $filters): array
    {
        $searchQuery = Contract::search($query)
            ->options(['showRankingScore' => true])
            ->orderBy('id')
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['file', 'tags']);
            })
            ->paginate(($filters['page'] ?? 1) * ($filters['limit'] ?? 20), 'page', 1);

        return ['results' => $this->formatter->formatContracts($results->getCollection()), 'total' => $results->total()];
    }

    protected function executeVoucherSearch(string $query, array $filters): array
    {
        $searchQuery = Voucher::search($query)
            ->options(['showRankingScore' => true])
            ->orderBy('id')
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['merchant', 'file', 'tags', 'user.preferences']);
            })
            ->paginate(($filters['page'] ?? 1) * ($filters['limit'] ?? 20), 'page', 1);

        return ['results' => $this->formatter->formatVouchers($results->getCollection()), 'total' => $results->total()];
    }

    protected function executeWarrantySearch(string $query, array $filters): array
    {
        $searchQuery = Warranty::search($query)
            ->options(['showRankingScore' => true])
            ->orderBy('id')
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['file', 'tags']);
            })
            ->paginate(($filters['page'] ?? 1) * ($filters['limit'] ?? 20), 'page', 1);

        return ['results' => $this->formatter->formatWarranties($results->getCollection()), 'total' => $results->total()];
    }

    protected function executeReturnPolicySearch(string $query, array $filters): array
    {
        $searchQuery = ReturnPolicy::search($query)
            ->options(['showRankingScore' => true])
            ->orderBy('id')
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['merchant', 'file', 'tags']);
            })
            ->paginate(($filters['page'] ?? 1) * ($filters['limit'] ?? 20), 'page', 1);

        return ['results' => $this->formatter->formatReturnPolicies($results->getCollection()), 'total' => $results->total()];
    }

    protected function executeBankStatementSearch(string $query, array $filters): array
    {
        $searchQuery = BankStatement::search($query)
            ->options(['showRankingScore' => true])
            ->orderBy('id')
            ->where('user_id', auth()->id());

        SearchFilterBuilder::apply($searchQuery, $filters);

        $results = $searchQuery
            ->query(function ($builder) {
                $builder->with(['file', 'tags']);
            })
            ->paginate(($filters['page'] ?? 1) * ($filters['limit'] ?? 20), 'page', 1);

        return ['results' => $this->formatter->formatBankStatements($results->getCollection()), 'total' => $results->total()];
    }
}
