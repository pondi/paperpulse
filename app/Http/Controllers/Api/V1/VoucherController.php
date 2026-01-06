<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\VoucherResource;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class VoucherController extends BaseApiController
{
    /**
     * List vouchers with optional filtering
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'voucher_type' => 'nullable|string|in:gift_card,payment_plan,store_credit,coupon,promo_code',
            'is_redeemed' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Voucher::where('user_id', $request->user()->id);

        if (isset($validated['voucher_type'])) {
            $query->where('voucher_type', $validated['voucher_type']);
        }

        if (isset($validated['is_redeemed'])) {
            $query->where('is_redeemed', $validated['is_redeemed']);
        }

        $query->with(['merchant', 'tags']);

        $vouchers = $query
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated(VoucherResource::collection($vouchers));
    }

    /**
     * Get detailed voucher information
     */
    public function show(Request $request, int $id)
    {
        try {
            $voucher = Voucher::where('user_id', $request->user()->id)
                ->with(['merchant', 'tags', 'file'])
                ->findOrFail($id);

            return $this->success(
                new VoucherResource($voucher),
                'Voucher details retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Voucher not found');
        }
    }

    /**
     * Mark voucher as redeemed
     */
    public function redeem(Request $request, int $id)
    {
        try {
            $voucher = Voucher::where('user_id', $request->user()->id)
                ->findOrFail($id);

            if ($voucher->is_redeemed) {
                return $this->error('Voucher already redeemed', 422);
            }

            if ($voucher->isExpired()) {
                return $this->error('Voucher has expired', 422);
            }

            $validated = $request->validate([
                'redemption_location' => 'nullable|string|max:255',
            ]);

            $voucher->update([
                'is_redeemed' => true,
                'redeemed_at' => now(),
                'redemption_location' => $validated['redemption_location'] ?? null,
            ]);

            return $this->success(
                new VoucherResource($voucher),
                'Voucher marked as redeemed successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Voucher not found');
        }
    }
}
