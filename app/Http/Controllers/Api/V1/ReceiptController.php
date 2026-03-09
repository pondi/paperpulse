<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ReceiptResource;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Builder;

class ReceiptController extends BaseEntityApiController
{
    protected function modelClass(): string
    {
        return Receipt::class;
    }

    protected function resourceClass(): string
    {
        return ReceiptResource::class;
    }

    protected function allowedSortFields(): array
    {
        return ['receipt_date', 'total_amount', 'created_at'];
    }

    protected function filterRules(): array
    {
        return [
            'merchant' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'currency' => 'nullable|string|max:3',
        ];
    }

    protected function indexWith(): array
    {
        return ['file', 'merchant', 'category', 'lineItems', 'tags'];
    }

    protected function applyFilters(Builder $query, array $validated): Builder
    {
        if (! empty($validated['merchant'])) {
            $query->whereHas('merchant', function ($q) use ($validated) {
                $q->where('name', 'like', '%'.$validated['merchant'].'%');
            });
        }

        if (! empty($validated['date_from'])) {
            $query->where('receipt_date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('receipt_date', '<=', $validated['date_to']);
        }

        if (! empty($validated['currency'])) {
            $query->where('currency', $validated['currency']);
        }

        return $query;
    }
}
