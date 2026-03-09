<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ContractResource;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Builder;

class ContractController extends BaseEntityApiController
{
    protected function modelClass(): string
    {
        return Contract::class;
    }

    protected function resourceClass(): string
    {
        return ContractResource::class;
    }

    protected function allowedSortFields(): array
    {
        return ['effective_date', 'expiry_date', 'contract_value', 'created_at'];
    }

    protected function filterRules(): array
    {
        return [
            'status' => 'nullable|string',
            'contract_type' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ];
    }

    protected function applyFilters(Builder $query, array $validated): Builder
    {
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['contract_type'])) {
            $query->where('contract_type', $validated['contract_type']);
        }

        if (! empty($validated['date_from'])) {
            $query->where('effective_date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('effective_date', '<=', $validated['date_to']);
        }

        return $query;
    }
}
