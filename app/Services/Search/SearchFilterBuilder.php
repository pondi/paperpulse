<?php

namespace App\Services\Search;

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Http\Request;
use Laravel\Scout\Builder;
use Meilisearch\Endpoints\Indexes;

class SearchFilterBuilder
{
    public static function apply(Builder $search, array $filters): Builder
    {
        $dateField = match ($search->model::class) {
            Receipt::class => 'receipt_date',
            Document::class => 'document_date',
            Invoice::class => 'invoice_date',
            Contract::class => 'effective_date',
            Voucher::class => 'expiry_date',
            Warranty::class => 'warranty_end_date',
            ReturnPolicy::class => 'return_deadline',
            BankStatement::class => 'statement_date',
        };
        $amountField = match ($search->model::class) {
            Contract::class => 'contract_value',
            Voucher::class => 'current_value',
            BankStatement::class => 'closing_balance',
            default => 'total_amount',
        };
        $clauses = [];
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $clauses[] = $dateField.' '.$operator.' '.str_replace('-', '', $filters[$key]);
            }
        }
        foreach (['amount_min' => '>=', 'amount_max' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $clauses[] = $amountField.' '.$operator.' '.(float) $filters[$key];
            }
        }
        $categoryField = $search->model instanceof Receipt ? 'receipt_category' : 'category_name';
        foreach (['category' => $categoryField, 'document_type' => 'document_type', 'collection_id' => 'collection_ids', 'vendor' => 'vendors'] as $key => $field) {
            if (isset($filters[$key])) {
                $clauses[] = $field.' = '.json_encode((string) $filters[$key], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }
        foreach (['tags' => 'tags', 'vendors' => 'vendors'] as $key => $field) {
            if (! empty($filters[$key])) {
                $clauses[] = $field.' IN '.json_encode(array_values($filters[$key]), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }
        if ($clauses === []) {
            return $search;
        }

        $search->callback = function (Indexes $index, string $query, array $options) use ($clauses): array {
            $options['filter'] = array_merge([$options['filter']], $clauses);

            return $index->rawSearch($query, $options);
        };

        return $search;
    }

    public static function build(Request $request): array
    {
        $filters = [
            'type' => $request->input('type', 'all'),
            'limit' => $request->input('limit', 20),
            'page' => $request->input('page', 1),
        ];

        foreach (['date_from', 'date_to', 'amount_min', 'amount_max', 'category', 'document_type', 'collection_id', 'vendor', 'vendors'] as $key) {
            if ($request->has($key)) {
                $filters[$key] = $request->input($key);
            }
        }

        if ($request->has('tags')) {
            $filters['tags'] = is_array($request->input('tags'))
                ? $request->input('tags')
                : explode(',', (string) $request->input('tags'));
        }

        return $filters;
    }
}
