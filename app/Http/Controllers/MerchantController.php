<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Services\LogoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

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
                    ->where('receipts.user_id', '=', auth()->id());
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
                DB::raw('SUM(CAST(receipts.total_amount AS DECIMAL(10,2))) as total_amount'),
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
            ->get()
            ->map(fn ($merchant) => [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'imageUrl' => $this->logoService->getImageUrl($merchant, $merchant->logo_data, $merchant->mime_type),
                'lastInvoice' => [
                    'date' => $merchant->last_receipt_date 
                        ? date('F j, Y', strtotime($merchant->last_receipt_date)) 
                        : 'Ingen kvitteringer',
                    'dateTime' => $merchant->last_receipt_date,
                    'amount' => number_format((float)$merchant->total_amount, 2, ',', ' ') . ' kr'
                ]
            ]);

        return Inertia::render('Receipt/Merchants', [
            'merchants' => $merchants
        ]);
    }

    public function updateLogo(Request $request, Merchant $merchant): RedirectResponse
    {
        // Verify user has access to this merchant through their receipts
        $hasAccess = $merchant->receipts()
            ->where('user_id', auth()->id())
            ->exists();
            
        if (!$hasAccess) {
            abort(403, 'Unauthorized access to merchant');
        }
        
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $file = $request->file('logo');
        $this->logoService->updateModelLogo($merchant, $file->get(), $file->getMimeType());

        return back();
    }
}
