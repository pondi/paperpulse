<?php

namespace App\Http\Controllers;

use App\Models\LineItem;
use App\Models\Vendor;
use App\Services\LogoService;
use App\Services\MonetarySummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
            ->join('line_items', fn ($join) => $join->on('vendors.id', '=', 'line_items.vendor_id')->whereNull('line_items.deleted_at'))
            ->join('receipts', function ($join) {
                $join->on('line_items.receipt_id', '=', 'receipts.id')
                    ->where('receipts.user_id', '=', auth()->id())->whereNull('receipts.deleted_at');
            })
            ->select([
                'vendors.id',
                'vendors.name',
                'vendors.website',
                'vendors.contact_email',
                'vendors.contact_phone',
                'vendors.description',
                'logos.logo_data',
                'logos.mime_type',
                DB::raw('MAX(receipts.receipt_date) as last_item_date'),
                DB::raw('COUNT(DISTINCT line_items.id) as total_items'),
            ])
            ->groupBy([
                'vendors.id',
                'vendors.name',
                'vendors.website',
                'vendors.contact_email',
                'vendors.contact_phone',
                'vendors.description',
                'logos.logo_data',
                'logos.mime_type',
            ])
            ->orderBy('vendors.name')->orderBy('vendors.id')->paginate(50);
        $currency = auth()->user()->preference('currency', 'NOK');
        $items = LineItem::whereIn('vendor_id', $vendors->getCollection()->pluck('id'))
            ->whereHas('receipt', fn ($query) => $query->where('user_id', auth()->id()))->with('receipt')->lazyById(200)
            ->map(function (LineItem $item): LineItem {
                $item->setAttribute('amount', $item->qty * $item->price);
                $item->setAttribute('currency', $item->receipt->currency);
                $item->setAttribute('date', $item->receipt->receipt_date);

                return $item;
            });
        $totals = app(MonetarySummaryService::class)->aggregate($items, ['total' => 'amount'], 'date', $currency,
            ['vendor' => fn ($item) => $item->vendor_id]);
        $vendors->through(fn (Vendor $vendor): array => [
            'id' => $vendor->id,
            'name' => $vendor->name,
            'imageUrl' => $this->logoService->getImageUrl($vendor, $vendor->logo_data, $vendor->mime_type),
            'website' => $vendor->website,
            'contact' => [
                'email' => $vendor->contact_email,
                'phone' => $vendor->contact_phone,
            ],
            'stats' => [
                'date' => $vendor->last_item_date
                    ? Date::parse($vendor->last_item_date)->format('F j, Y')
                    : 'No items',
                'dateTime' => $vendor->last_item_date ? Date::parse($vendor->last_item_date)->toDateString() : null,
                'totalItems' => $vendor->total_items,
                'totalValue' => $totals['groups']['vendor'][$vendor->id]['total'],
                'currency' => $currency,
                'status' => $vendor->total_items > 0 ? 'Active' : 'No items',
            ],
        ]);

        return Inertia::render('Receipt/Vendors', [
            'vendors' => $vendors,
        ]);
    }

    public function show(Vendor $vendor): Response
    {
        // Verify user has access to this vendor through their receipts
        $hasAccess = $vendor->lineItems()
            ->whereHas('receipt', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Unauthorized access to vendor');
        }

        $items = $vendor->lineItems()
            ->whereHas('receipt', fn ($query) => $query->where('user_id', auth()->id()))
            ->with('receipt.merchant')
            ->latest('id')
            ->paginate(50)
            ->through(fn (LineItem $item): array => [
                'id' => $item->id,
                'text' => $item->text,
                'qty' => $item->qty,
                'price' => $item->price,
                'currency' => $item->receipt->currency,
                'receipt_id' => $item->receipt_id,
                'receipt_date' => $item->receipt->receipt_date,
                'merchant' => $item->receipt->merchant?->name,
            ]);

        return Inertia::render('Receipt/VendorShow', [
            'vendor' => $vendor->only(['id', 'name', 'description', 'website', 'contact_email', 'contact_phone']),
            'items' => $items,
        ]);
    }

    public function updateLogo(Request $request, Vendor $vendor): RedirectResponse
    {
        // Verify user has access to this vendor through their receipts
        $hasAccess = $vendor->lineItems()
            ->whereHas('receipt', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Unauthorized access to vendor');
        }

        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        $file = $request->file('logo');
        $this->logoService->updateModelLogo($vendor, $file->get(), $file->getMimeType());

        return back();
    }
}
