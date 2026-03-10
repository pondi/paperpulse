<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

class InvoiceController extends BaseEntityApiController
{
    protected function modelClass(): string
    {
        return Invoice::class;
    }

    protected function resourceClass(): string
    {
        return InvoiceResource::class;
    }

    protected function allowedSortFields(): array
    {
        return ['invoice_date', 'due_date', 'total_amount', 'created_at'];
    }

    protected function filterRules(): array
    {
        return [
            'payment_status' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ];
    }

    protected function indexWith(): array
    {
        return ['merchant', 'category', 'lineItems', 'tags'];
    }

    protected function applyFilters(Builder $query, array $validated): Builder
    {
        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        if (! empty($validated['date_from'])) {
            $query->where('invoice_date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('invoice_date', '<=', $validated['date_to']);
        }

        return $query;
    }
}
