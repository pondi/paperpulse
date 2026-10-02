<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Receipt;
use App\Services\LogoService;
use App\Services\MonetarySummaryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MerchantController extends Controller
{
    public function __construct(
        private readonly LogoService $logoService
    ) {}

    public function index(): Response
    {
        $merchants = Merchant::query()
            ->leftJoin('logos', function ($join) {
                $join->on('merchants.id', '=', 'logos.logoable_id')
                    ->where('logos.logoable_type', '=', Merchant::class);
            })
            ->join('receipts', function ($join) {
                $join->on('merchants.id', '=', 'receipts.merchant_id')
                    ->where('receipts.user_id', '=', auth()->id())
                    ->whereNull('receipts.deleted_at');
            })
            ->select(
                'merchants.id',
                'merchants.name',
                'merchants.address',
                'merchants.vat_number',
                'merchants.email',
                'merchants.phone',
                'merchants.website',
                'logos.logo_data',
                'logos.mime_type',
                DB::raw('MAX(receipts.receipt_date) as last_receipt_date'),
                DB::raw('COUNT(receipts.id) as receipt_count')
            )
            ->groupBy(
                'merchants.id',
                'merchants.name',
                'merchants.address',
                'merchants.vat_number',
                'merchants.email',
                'merchants.phone',
                'merchants.website',
                'logos.logo_data',
                'logos.mime_type'
            )
            ->orderBy('merchants.name')->orderBy('merchants.id')->paginate(50);
        $currency = auth()->user()->preference('currency', 'NOK');
        $totals = app(MonetarySummaryService::class)->aggregate(Receipt::where('user_id', auth()->id())
            ->whereIn('merchant_id', $merchants->getCollection()->pluck('id'))->lazyById(200), ['total' => 'total_amount'], 'receipt_date', $currency,
            ['merchant' => fn ($receipt) => $receipt->merchant_id]);
        $merchants->through(fn ($merchant) => [
            'id' => $merchant->id,
            'name' => $merchant->name,
            'imageUrl' => $this->logoService->getImageUrl($merchant, $merchant->logo_data, $merchant->mime_type),
            'lastInvoice' => [
                'date' => $merchant->last_receipt_date
                    ? Carbon::parse($merchant->last_receipt_date)->format('F j, Y')
                    : 'Ingen kvitteringer',
                'dateTime' => $merchant->last_receipt_date ? Carbon::parse($merchant->last_receipt_date)->toDateString() : null,
                'amount' => $totals['groups']['merchant'][$merchant->id]['total'],
                'currency' => $currency,
            ],
        ]);

        return Inertia::render('Receipt/Merchants', [
            'merchants' => $merchants,
        ]);
    }

    public function updateLogo(Request $request, Merchant $merchant): RedirectResponse
    {
        // Verify user has access to this merchant through their receipts
        $hasAccess = $merchant->receipts()
            ->where('user_id', auth()->id())
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Unauthorized access to merchant');
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $file = $request->file('logo');
        $this->logoService->updateModelLogo($merchant, $file->get(), $file->getMimeType());

        return back();
    }
}
