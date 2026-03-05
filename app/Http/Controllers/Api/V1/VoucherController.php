<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\VoucherResource;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:expiry_date,original_value,current_value,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'voucher_type' => 'nullable|string',
            'is_redeemed' => 'nullable|boolean',
        ]);

        $query = Voucher::query()
            ->with(['merchant', 'tags']);

        if (! empty($validated['voucher_type'])) {
            $query->where('voucher_type', $validated['voucher_type']);
        }

        if (isset($validated['is_redeemed'])) {
            $query->where('is_redeemed', $validated['is_redeemed']);
        }

        $sortField = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $vouchers = $query->paginate($validated['per_page'] ?? 25);

        return $this->paginated(VoucherResource::collection($vouchers), 'Vouchers retrieved');
    }

    public function show(int $id): JsonResponse
    {
        $voucher = Voucher::query()
            ->with(['merchant', 'tags'])
            ->find($id);

        if (! $voucher) {
            return $this->notFound('Voucher not found');
        }

        return $this->success(new VoucherResource($voucher), 'Voucher retrieved');
    }
}
