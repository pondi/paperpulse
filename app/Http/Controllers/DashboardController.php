<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\MonetarySummaryService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $currency = auth()->user()->preference('currency', 'NOK');
        $summary = app(MonetarySummaryService::class)->aggregate(
            Receipt::where('user_id', $userId)->lazyById(200), ['total' => 'total_amount'], 'receipt_date', $currency);
        $stats = [
            'receiptCount' => $summary['count'], 'totalAmount' => $summary['amounts']['total'], 'summaryCurrency' => $currency,
            'merchantCount' => Merchant::whereHas('receipts', fn ($query) => $query->where('user_id', $userId))->count(),
        ];

        // Recent receipts are not cached — always fresh for dashboard relevance
        $recentReceipts = Receipt::with('merchant')
            ->where('user_id', $userId)
            ->orderBy('receipt_date', 'desc')
            ->take(5)
            ->get()->map(fn (Receipt $receipt): array => [
                'id' => $receipt->id, 'file_id' => $receipt->file_id, 'receipt_date' => $receipt->receipt_date?->toDateString(),
                'merchant' => $receipt->merchant, 'total_amount' => $receipt->total_amount,
                'currency' => $receipt->currency, 'receipt_category' => $receipt->receipt_category,
            ]);

        $today = auth()->user()->currentDate();
        $window = [$today->toDateString(), $today->copy()->addDays(30)->toDateString()];
        $vouchers = Voucher::where('user_id', $userId)->where('is_redeemed', false)->whereBetween('expiry_date', $window);
        $warranties = Warranty::where('user_id', $userId)->whereBetween('warranty_end_date', $window);
        $expiryWidgets = [
            'expiringVouchers' => [
                'total' => (clone $vouchers)->count(),
                'items' => $vouchers->with('merchant')->orderBy('expiry_date')->orderBy('id')->limit(5)->get()->map(fn (Voucher $voucher): array => [
                    'id' => $voucher->id, 'merchant' => $voucher->merchant, 'code' => $voucher->code,
                    'current_value' => $voucher->current_value, 'currency' => $voucher->currency,
                    'expiry_date' => $voucher->expiry_date->toDateString(),
                    'days_remaining' => (int) Carbon::parse($today->toDateString(), 'UTC')->diffInDays(Carbon::parse($voucher->expiry_date->toDateString(), 'UTC')),
                ]),
            ],
            'endingWarranties' => [
                'total' => (clone $warranties)->count(),
                'items' => $warranties->orderBy('warranty_end_date')->orderBy('id')->limit(5)->get()->map(fn (Warranty $warranty): array => [
                    'id' => $warranty->id, 'product_name' => $warranty->product_name, 'manufacturer' => $warranty->manufacturer,
                    'warranty_end_date' => $warranty->warranty_end_date->toDateString(),
                    'days_remaining' => (int) Carbon::parse($today->toDateString(), 'UTC')->diffInDays(Carbon::parse($warranty->warranty_end_date->toDateString(), 'UTC')),
                ]),
            ],
        ];

        return Inertia::render('Dashboard', [
            ...$stats,
            ...$expiryWidgets,
            'recentReceipts' => $recentReceipts,
        ]);
    }
}
