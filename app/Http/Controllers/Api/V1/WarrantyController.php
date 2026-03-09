<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\WarrantyResource;
use App\Models\Warranty;
use Illuminate\Database\Eloquent\Builder;

class WarrantyController extends BaseEntityApiController
{
    protected function modelClass(): string
    {
        return Warranty::class;
    }

    protected function resourceClass(): string
    {
        return WarrantyResource::class;
    }

    protected function allowedSortFields(): array
    {
        return ['warranty_end_date', 'purchase_date', 'created_at'];
    }

    protected function filterRules(): array
    {
        return [
            'warranty_type' => 'nullable|string',
            'manufacturer' => 'nullable|string|max:255',
        ];
    }

    protected function applyFilters(Builder $query, array $validated): Builder
    {
        if (! empty($validated['warranty_type'])) {
            $query->where('warranty_type', $validated['warranty_type']);
        }

        if (! empty($validated['manufacturer'])) {
            $query->where('manufacturer', 'like', '%'.$validated['manufacturer'].'%');
        }

        return $query;
    }
}
