<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        
        // Get total amount and count of receipts for current user
        $receiptStats = Receipt::where('user_id', $userId)
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as total_amount')
            )->first();

        // Get count of unique merchants for current user
        $merchantCount = Merchant::whereHas('receipts', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->count();

        // Get recent receipts for current user
        $recentReceipts = Receipt::with('merchant')
            ->where('user_id', $userId)
            ->orderBy('receipt_date', 'desc')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'receiptCount' => $receiptStats->count ?? 0,
            'totalAmount' => (float)($receiptStats->total_amount ?? 0),
            'merchantCount' => $merchantCount,
            'recentReceipts' => $recentReceipts
        ]);
    }
} 