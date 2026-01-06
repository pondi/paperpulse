<?php

namespace App\Http\Controllers;

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
            ->with(['merchant', 'file'])
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function ($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_type' => $voucher->voucher_type,
                    'code' => $voucher->code,
                    'merchant_name' => $voucher->merchant?->name,
                    'issue_date' => $voucher->issue_date?->format('Y-m-d'),
                    'expiry_date' => $voucher->expiry_date?->format('Y-m-d'),
                    'original_value' => $voucher->original_value,
                    'current_value' => $voucher->current_value,
                    'currency' => $voucher->currency,
                    'installment_count' => $voucher->installment_count,
                    'monthly_payment' => $voucher->monthly_payment,
                    'is_redeemed' => $voucher->is_redeemed,
                    'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
                    'file_id' => $voucher->file_id,
                ];
            });

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
            'voucher' => [
                'id' => $voucher->id,
                'voucher_type' => $voucher->voucher_type,
                'code' => $voucher->code,
                'barcode' => $voucher->barcode,
                'qr_code' => $voucher->qr_code,
                'merchant' => $voucher->merchant ? [
                    'id' => $voucher->merchant->id,
                    'name' => $voucher->merchant->name,
                ] : null,
                'issue_date' => $voucher->issue_date?->format('Y-m-d'),
                'expiry_date' => $voucher->expiry_date?->format('Y-m-d'),
                'original_value' => $voucher->original_value,
                'current_value' => $voucher->current_value,
                'currency' => $voucher->currency,
                'installment_count' => $voucher->installment_count,
                'monthly_payment' => $voucher->monthly_payment,
                'first_payment_date' => $voucher->first_payment_date?->format('Y-m-d'),
                'final_payment_date' => $voucher->final_payment_date?->format('Y-m-d'),
                'is_redeemed' => $voucher->is_redeemed,
                'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
                'redemption_location' => $voucher->redemption_location,
                'terms_and_conditions' => $voucher->terms_and_conditions,
                'restrictions' => $voucher->restrictions,
                'file_id' => $voucher->file_id,
                'tags' => $voucher->tags,
            ],
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
