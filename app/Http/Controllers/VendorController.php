<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\LogoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;

class VendorController extends Controller
{
    public function __construct(
        private readonly LogoService $logoService
    ) {}

    public function index(): Response
    {
        $vendors = Vendor::query()
            ->leftJoin('logos', function ($join) {
                $join->on('vendors.id', '=', 'logos.logoable_id')
                    ->where('logos.logoable_type', '=', Vendor::class);
            })
            ->join('line_items', 'vendors.id', '=', 'line_items.vendor_id')
            ->join('receipts', 'line_items.receipt_id', '=', 'receipts.id')
            ->select([
                'vendors.id',
                'vendors.name',
                'vendors.website',
                'vendors.contact_email',
                'vendors.contact_phone',
                'vendors.description',
                'logos.logo_data',
                'logos.mime_type',
                DB::raw('SUM(CAST((line_items.qty * line_items.price) AS DECIMAL(10,2))) as total_value'),
                DB::raw('MAX(receipts.receipt_date) as last_item_date'),
                DB::raw('COUNT(DISTINCT line_items.id) as total_items')
            ])
            ->groupBy([
                'vendors.id',
                'vendors.name',
                'vendors.website',
                'vendors.contact_email',
                'vendors.contact_phone',
                'vendors.description',
                'logos.logo_data',
                'logos.mime_type'
            ])
            ->get()
            ->map(fn (Vendor $vendor): array => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'imageUrl' => $this->logoService->getImageUrl($vendor, $vendor->logo_data, $vendor->mime_type),
                'website' => $vendor->website,
                'contact' => [
                    'email' => $vendor->contact_email,
                    'phone' => $vendor->contact_phone
                ],
                'stats' => [
                    'date' => $vendor->last_item_date 
                        ? Date::parse($vendor->last_item_date)->format('F j, Y')
                        : 'No items',
                    'dateTime' => $vendor->last_item_date,
                    'totalItems' => $vendor->total_items,
                    'totalValue' => $vendor->total_value 
                        ? number_format((float)$vendor->total_value, 2) . ' kr' 
                        : '0.00 kr',
                    'status' => $vendor->total_items > 0 ? 'Active' : 'No items'
                ]
            ]);

        return Inertia::render('Receipt/Vendors', [
            'vendors' => $vendors
        ]);
    }

    public function show(Vendor $vendor): Response
    {
        $vendor->load(['lineItems' => fn ($query) => $query->with('receipt')]);

        return Inertia::render('Receipt/VendorDetails', [
            'vendor' => $vendor
        ]);
    }

    public function updateLogo(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048']
        ]);

        $file = $request->file('logo');
        $this->logoService->updateModelLogo($vendor, $file->get(), $file->getMimeType());

        return back();
    }
} 