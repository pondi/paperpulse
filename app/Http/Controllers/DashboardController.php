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
        // Get total amount and count of receipts
        $receiptStats = Receipt::select(
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total_amount) as total_amount')
        )->first();

        // Get count of unique merchants
        $merchantCount = Merchant::count();

        // Get recent receipts
        $recentReceipts = Receipt::with('merchant')
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