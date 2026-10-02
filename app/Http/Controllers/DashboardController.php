<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Receipt;
use App\Services\MonetarySummaryService;
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
                'id' => $receipt->id, 'receipt_date' => $receipt->receipt_date?->toDateString(),
                'merchant' => $receipt->merchant, 'total_amount' => $receipt->total_amount,
                'currency' => $receipt->currency, 'receipt_category' => $receipt->receipt_category,
            ]);

        return Inertia::render('Dashboard', [
            ...$stats,
            'recentReceipts' => $recentReceipts,
        ]);
    }
}
