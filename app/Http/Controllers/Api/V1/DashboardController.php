<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    public function getWidgets(Request $request)
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $days = $validated['days'] ?? 30;
        $limit = $validated['limit'] ?? 5;
        $userId = $request->user()->id;

        $now = now();
        $today = $now->toDateString();
        $endDate = $now->copy()->addDays($days)->toDateString();
        $baseDate = $now->copy()->startOfDay();

        $voucherQuery = Voucher::where('user_id', $userId)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $endDate)
            ->where('is_redeemed', false)
            ->with('merchant')
            ->orderBy('expiry_date');

        $voucherCount = (clone $voucherQuery)->count();

        $voucherItems = $voucherQuery
            ->limit($limit)
            ->get()
            ->map(function ($voucher) use ($baseDate) {
                $expiryDate = $voucher->expiry_date;
                $daysRemaining = $expiryDate
                    ? $baseDate->diffInDays($expiryDate->copy()->startOfDay())
                    : null;

                return [
                    'id' => $voucher->id,
                    'voucher_type' => $voucher->voucher_type,
                    'code' => $voucher->code,
                    'expiry_date' => $expiryDate?->toDateString(),
                    'days_remaining' => $daysRemaining,
                    'current_value' => $voucher->current_value,
                    'currency' => $voucher->currency,
                    'merchant' => $voucher->merchant
                        ? [
                            'id' => $voucher->merchant->id,
                            'name' => $voucher->merchant->name,
                        ]
                        : null,
                ];
            })
            ->values();

        $warrantyQuery = Warranty::where('user_id', $userId)
            ->whereNotNull('warranty_end_date')
            ->whereDate('warranty_end_date', '>=', $today)
            ->whereDate('warranty_end_date', '<=', $endDate)
            ->orderBy('warranty_end_date');

        $warrantyCount = (clone $warrantyQuery)->count();

        $warrantyItems = $warrantyQuery
            ->limit($limit)
            ->get()
            ->map(function ($warranty) use ($baseDate) {
                $endDateValue = $warranty->warranty_end_date;
                $daysRemaining = $endDateValue
                    ? $baseDate->diffInDays($endDateValue->copy()->startOfDay())
                    : null;

                return [
                    'id' => $warranty->id,
                    'product_name' => $warranty->product_name,
                    'manufacturer' => $warranty->manufacturer,
                    'warranty_end_date' => $endDateValue?->toDateString(),
                    'days_remaining' => $daysRemaining,
                ];
            })
            ->values();

        return $this->success([
            'days' => $days,
            'limit' => $limit,
            'expiring_vouchers' => [
                'count' => $voucherCount,
                'items' => $voucherItems,
            ],
            'ending_warranties' => [
                'count' => $warrantyCount,
                'items' => $warrantyItems,
            ],
        ], 'Dashboard widgets retrieved successfully');
    }
}
