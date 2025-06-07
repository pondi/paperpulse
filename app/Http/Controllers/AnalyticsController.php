<?php

namespace App\Http\Controllers;

use App\Models\LineItem;
use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $period = $request->get('period', 'month'); // month, quarter, year

        $startDate = $this->getStartDate($period);
        $endDate = Carbon::now();

        // Overall statistics
        $totalReceipts = Receipt::where('user_id', $userId)->count();
        $totalAmount = Receipt::where('user_id', $userId)->sum('total_amount');
        $totalTax = Receipt::where('user_id', $userId)->sum('tax_amount');
        $totalMerchants = Receipt::where('user_id', $userId)
            ->distinct('merchant_id')
            ->count('merchant_id');

        // Period statistics
        $periodReceipts = Receipt::where('user_id', $userId)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->count();

        $periodAmount = Receipt::where('user_id', $userId)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->sum('total_amount');

        // Spending by category
        $spendingByCategory = Receipt::where('user_id', $userId)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->select('receipt_category', DB::raw('SUM(total_amount) as total'))
            ->groupBy('receipt_category')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->receipt_category ?: 'Uncategorized',
                    'total' => (float) $item->total,
                ];
            });

        // Top merchants
        $topMerchants = Receipt::where('user_id', $userId)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->select('merchant_id', DB::raw('COUNT(*) as receipt_count'), DB::raw('SUM(total_amount) as total'))
            ->with('merchant')
            ->groupBy('merchant_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'merchant' => $item->merchant?->name ?: 'Unknown',
                    'receipt_count' => $item->receipt_count,
                    'total' => (float) $item->total,
                ];
            });

        // Monthly spending trend
        $monthlyTrend = Receipt::where('user_id', $userId)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->select(
                DB::raw("TO_CHAR(receipt_date, 'YYYY-MM') as month"),
                DB::raw('COUNT(*) as receipt_count'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => Carbon::parse($item->month.'-01')->format('M Y'),
                    'receipt_count' => $item->receipt_count,
                    'total' => (float) $item->total,
                ];
            });

        // Recent receipts
        $recentReceipts = Receipt::where('user_id', $userId)
            ->with('merchant')
            ->orderBy('receipt_date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($receipt) {
                return [
                    'id' => $receipt->id,
                    'merchant' => $receipt->merchant?->name ?: 'Unknown',
                    'date' => $receipt->receipt_date ? Carbon::parse($receipt->receipt_date)->format('Y-m-d') : null,
                    'total' => $receipt->total_amount,
                    'category' => $receipt->receipt_category ?: 'Uncategorized',
                ];
            });

        // Average receipt value
        $avgReceiptValue = $periodReceipts > 0 ? $periodAmount / $periodReceipts : 0;

        // Most purchased items
        $topItems = LineItem::whereHas('receipt', function ($query) use ($userId, $startDate, $endDate) {
            $query->where('user_id', $userId)
                ->whereBetween('receipt_date', [$startDate, $endDate]);
        })
            ->select('text', DB::raw('SUM(qty) as total_qty'), DB::raw('COUNT(*) as purchase_count'))
            ->groupBy('text')
            ->orderByDesc('purchase_count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->text,
                    'quantity' => $item->total_qty,
                    'purchases' => $item->purchase_count,
                ];
            });

        return Inertia::render('Analytics/Dashboard', [
            'stats' => [
                'total_receipts' => $totalReceipts,
                'total_amount' => round($totalAmount, 2),
                'total_tax' => round($totalTax, 2),
                'total_merchants' => $totalMerchants,
                'period_receipts' => $periodReceipts,
                'period_amount' => round($periodAmount, 2),
                'avg_receipt_value' => round($avgReceiptValue, 2),
            ],
            'charts' => [
                'spending_by_category' => $spendingByCategory,
                'top_merchants' => $topMerchants,
                'monthly_trend' => $monthlyTrend,
                'top_items' => $topItems,
            ],
            'recent_receipts' => $recentReceipts,
            'current_period' => $period,
        ]);
    }

    private function getStartDate($period)
    {
        switch ($period) {
            case 'month':
                return Carbon::now()->subMonth();
            case 'quarter':
                return Carbon::now()->subQuarter();
            case 'year':
                return Carbon::now()->subYear();
            default:
                return Carbon::now()->subMonth();
        }
    }
}
