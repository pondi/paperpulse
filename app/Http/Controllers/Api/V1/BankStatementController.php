<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\BankStatementResource;
use App\Models\BankStatement;
use Illuminate\Database\Eloquent\Builder;

class BankStatementController extends BaseEntityApiController
{
    protected function modelClass(): string
    {
        return BankStatement::class;
    }

    protected function resourceClass(): string
    {
        return BankStatementResource::class;
    }

    protected function allowedSortFields(): array
    {
        return ['statement_date', 'opening_balance', 'closing_balance', 'created_at'];
    }

    protected function filterRules(): array
    {
        return [
            'bank_name' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ];
    }

    protected function showWith(): array
    {
        return ['transactions', 'tags'];
    }

    protected function entityLabel(): string
    {
        return 'Bank statement';
    }

    protected function applyFilters(Builder $query, array $validated): Builder
    {
        if (! empty($validated['bank_name'])) {
            $query->where('bank_name', 'like', '%'.$validated['bank_name'].'%');
        }

        if (! empty($validated['date_from'])) {
            $query->where('statement_date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('statement_date', '<=', $validated['date_to']);
        }

        return $query;
    }
}
