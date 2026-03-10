<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\VoucherResource;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;

class VoucherController extends BaseEntityApiController
{
    protected function modelClass(): string
    {
        return Voucher::class;
    }

    protected function resourceClass(): string
    {
        return VoucherResource::class;
    }

    protected function allowedSortFields(): array
    {
        return ['expiry_date', 'original_value', 'current_value', 'created_at'];
    }

    protected function filterRules(): array
    {
        return [
            'voucher_type' => 'nullable|string',
            'is_redeemed' => 'nullable|boolean',
        ];
    }

    protected function indexWith(): array
    {
        return ['merchant', 'tags'];
    }

    protected function applyFilters(Builder $query, array $validated): Builder
    {
        if (! empty($validated['voucher_type'])) {
            $query->where('voucher_type', $validated['voucher_type']);
        }

        if (isset($validated['is_redeemed'])) {
            $query->where('is_redeemed', $validated['is_redeemed']);
        }

        return $query;
    }
}
