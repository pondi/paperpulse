<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::with('lineItems')
            ->select('vendors.*')
            ->addSelect([
                'total_items' => DB::raw('(SELECT COUNT(*) FROM line_items WHERE line_items.vendor_id = vendors.id)'),
                'total_value' => DB::raw('(SELECT SUM(CAST((qty * price) AS DECIMAL(10,2))) FROM line_items WHERE line_items.vendor_id = vendors.id)'),
                'last_item_date' => DB::raw('(SELECT receipts.receipt_date FROM line_items JOIN receipts ON line_items.receipt_id = receipts.id WHERE line_items.vendor_id = vendors.id ORDER BY receipts.receipt_date DESC LIMIT 1)')
            ])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('line_items')
                    ->whereColumn('line_items.vendor_id', 'vendors.id');
            })
            ->get()
            ->map(function ($vendor) {
                return [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'imageUrl' => $vendor->logo_url,
                    'website' => $vendor->website,
                    'contact' => [
                        'email' => $vendor->contact_email,
                        'phone' => $vendor->contact_phone
                    ],
                    'stats' => [
                        'date' => $vendor->last_item_date ? date('F j, Y', strtotime($vendor->last_item_date)) : 'No items',
                        'dateTime' => $vendor->last_item_date,
                        'totalItems' => $vendor->total_items,
                        'totalValue' => $vendor->total_value ? number_format($vendor->total_value, 2) . ' kr' : '0.00 kr',
                        'status' => $vendor->total_items > 0 ? 'Active' : 'No items'
                    ]
                ];
            });

        return Inertia::render('Receipt/Vendors', [
            'vendors' => $vendors
        ]);
    }

    public function show(Vendor $vendor)
    {
        $vendor->load(['lineItems' => function ($query) {
            $query->with('receipt');
        }]);

        return Inertia::render('Receipt/VendorDetails', [
            'vendor' => $vendor
        ]);
    }

    public function updateLogo(Request $request, Vendor $vendor)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $file = $request->file('logo');
        $fileContent = file_get_contents($file->getRealPath());
        $logoData = base64_encode($fileContent);
        $mimeType = $file->getMimeType();

        DB::table('vendor_logos')->updateOrInsert(
            ['vendor_id' => $vendor->id],
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