<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Receipt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $stats = Cache::remember("dashboard_stats:{$userId}", 300, function () use ($userId) {
            $receiptStats = Receipt::where('user_id', $userId)
                ->select(
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(total_amount) as total_amount')
                )->first();

            $merchantCount = Merchant::whereHas('receipts', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->count();

            return [
                'receiptCount' => $receiptStats->count ?? 0,
                'totalAmount' => (float) ($receiptStats->total_amount ?? 0),
                'merchantCount' => $merchantCount,
            ];
        });

        // Recent receipts are not cached — always fresh for dashboard relevance
        $recentReceipts = Receipt::with('merchant')
            ->where('user_id', $userId)
            ->orderBy('receipt_date', 'desc')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            ...$stats,
            'recentReceipts' => $recentReceipts,
        ]);
    }
}
