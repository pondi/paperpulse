<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Merchant;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class MerchantController extends Controller
{
    public function index()
    {
        $merchants = DB::table('merchants')
            ->leftJoin('merchant_logos', 'merchants.id', '=', 'merchant_logos.merchant_id')
            ->join('receipts', 'merchants.id', '=', 'receipts.merchant_id')
            ->select(
                'merchants.id',
                'merchants.name',
                'merchant_logos.logo_data',
                'merchant_logos.mime_type',
                DB::raw('SUM(CAST(receipts.total_amount AS DECIMAL(10,2))) as total_amount'),
                DB::raw('MAX(receipts.receipt_date) as last_receipt_date'),
                DB::raw('COUNT(receipts.id) as receipt_count')
            )
            ->groupBy('merchants.id', 'merchants.name', 'merchant_logos.logo_data', 'merchant_logos.mime_type')
            ->get()
            ->map(function ($merchant) {
                return [
                    'id' => $merchant->id,
                    'name' => $merchant->name,
                    'imageUrl' => $merchant->logo_data 
                        ? 'data:' . $merchant->mime_type . ';base64,' . stream_get_contents($merchant->logo_data)
                        : 'https://ui-avatars.com/api/?name=' . urlencode($merchant->name) . '&color=7F9CF5&background=EBF4FF',
                    'lastInvoice' => [
                        'date' => $merchant->last_receipt_date ? date('F j, Y', strtotime($merchant->last_receipt_date)) : 'Ingen kvitteringer',
                        'dateTime' => $merchant->last_receipt_date,
                        'amount' => number_format((float)$merchant->total_amount, 2, ',', ' ') . ' kr'
                    ]
                ];
            });

        return Inertia::render('Receipt/Merchants', [
            'merchants' => $merchants
        ]);
    }

    public function updateLogo(Request $request, Merchant $merchant)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $file = $request->file('logo');
        $fileContent = file_get_contents($file->getRealPath());
        $logoData = base64_encode($fileContent);
        $mimeType = $file->getMimeType();

        DB::table('merchant_logos')->updateOrInsert(
            ['merchant_id' => $merchant->id],
            [
                'logo_data' => $logoData,
                'mime_type' => $mimeType,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        return back();
    }
}
