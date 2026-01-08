<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Services\Vouchers\VoucherTransformer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    /**
     * Display a listing of vouchers
     */
    public function index(Request $request): Response
    {
        $vouchers = Voucher::where('user_id', $request->user()->id)
            ->with(['merchant'])
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(fn (Voucher $voucher) => VoucherTransformer::forIndex($voucher));

        return Inertia::render('Vouchers/Index', [
            'vouchers' => $vouchers,
        ]);
    }

    /**
     * Display the specified voucher
     */
    public function show(Request $request, Voucher $voucher): Response
    {
        // Authorization check
        if ($voucher->user_id !== $request->user()->id) {
            abort(403);
        }

        $voucher->load(['merchant', 'file', 'tags']);

        return Inertia::render('Vouchers/Show', [
            'voucher' => VoucherTransformer::forShow($voucher),
        ]);
    }

    /**
     * Mark voucher as redeemed
     */
    public function redeem(Request $request, Voucher $voucher)
    {
        // Authorization check
        if ($voucher->user_id !== $request->user()->id) {
            abort(403);
        }

        $voucher->update([
            'is_redeemed' => true,
            'redeemed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Voucher marked as redeemed');
    }
}
