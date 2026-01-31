<?php

namespace App\Http\Controllers;

use App\Http\Resources\Inertia\VoucherInertiaResource;
use App\Models\Voucher;
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
            ->map(fn (Voucher $voucher) => VoucherInertiaResource::forIndex($voucher)->toArray(request()));

        return Inertia::render('Vouchers/Index', [
            'vouchers' => $vouchers,
        ]);
    }

    /**
     * Display the specified voucher
     */
    public function show(Request $request, Voucher $voucher): Response
    {
        $this->authorize('view', $voucher);

        $voucher->load(['merchant', 'file', 'tags']);

        return Inertia::render('Vouchers/Show', [
            'voucher' => VoucherInertiaResource::forShow($voucher)->toArray(request()),
        ]);
    }

    /**
     * Mark voucher as redeemed
     */
    public function redeem(Request $request, Voucher $voucher)
    {
        $this->authorize('update', $voucher);

        $voucher->update([
            'is_redeemed' => true,
            'redeemed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Voucher marked as redeemed');
    }
}
