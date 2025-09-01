<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\DocumentService;
use App\Services\ReceiptService;
use App\Traits\SanitizesInput;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReceiptController extends Controller
{
    use SanitizesInput;

    protected DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function index()
    {
        $user = auth()->user();
        $perPage = $user->preference('receipts_per_page', 20);
        $sortOption = $user->preference('default_sort', 'date_desc');

        // Build query with sorting
        $query = Receipt::with(['merchant', 'file', 'lineItems', 'category', 'tags'])
            ->where('user_id', $user->id);

        // Apply sorting based on preference
        switch ($sortOption) {
            case 'date_asc':
                $query->orderBy('receipt_date', 'asc');
                break;
            case 'amount_desc':
                $query->orderBy('total_amount', 'desc');
                break;
            case 'amount_asc':
                $query->orderBy('total_amount', 'asc');
                break;
            case 'merchant_asc':
                $query->leftJoin('merchants', 'receipts.merchant_id', '=', 'merchants.id')
                    ->orderBy('merchants.name', 'asc')
                    ->select('receipts.*');
                break;
            case 'merchant_desc':
                $query->leftJoin('merchants', 'receipts.merchant_id', '=', 'merchants.id')
                    ->orderBy('merchants.name', 'desc')
                    ->select('receipts.*');
                break;
            case 'date_desc':
            default:
                $query->orderBy('receipt_date', 'desc');
                break;
        }

        $receipts = $query->paginate($perPage);

        // Transform the items in the paginator
        $receipts->getCollection()->transform(function ($receipt) {
            return [
                'id' => $receipt->id,
                'merchant' => $receipt->merchant,
                'category' => $receipt->category,
                'category_id' => $receipt->category_id,
                'receipt_date' => $receipt->receipt_date,
                'tax_amount' => $receipt->tax_amount,
                'total_amount' => $receipt->total_amount,
                'currency' => $receipt->currency,
                'receipt_category' => $receipt->receipt_category,
                'receipt_description' => $receipt->receipt_description,
                'file' => $receipt->file ? [
                    'id' => $receipt->file->id,
                    'url' => route('receipts.showImage', $receipt->id),
                    'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null,
                    'extension' => $receipt->file->fileExtension ?? 'jpg',
                    'mime_type' => $receipt->file->mime_type,
                ] : null,
                'lineItems' => $receipt->lineItems ? $receipt->lineItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->text,
                        'sku' => $item->sku,
                        'quantity' => $item->qty,
                        'unit_price' => $item->price,
                        'total_amount' => $item->total,
                    ];
                }) : [],
                'tags' => $receipt->tags ? $receipt->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ];
                }) : [],
            ];
        });

        $categories = auth()->user()->categories()
            ->active()
            ->ordered()
            ->get();

        return Inertia::render('Receipt/Index', [
            'receipts' => $receipts->items(),
            'categories' => $categories,
            'pagination' => [
                'current_page' => $receipts->currentPage(),
                'last_page' => $receipts->lastPage(),
                'per_page' => $receipts->perPage(),
                'total' => $receipts->total(),
                'from' => $receipts->firstItem(),
                'to' => $receipts->lastItem(),
            ],
            'user_preferences' => [
                'receipts_per_page' => $perPage,
                'default_sort' => $sortOption,
                'receipt_list_view' => $user->preference('receipt_list_view', 'grid'),
            ],
        ]);
    }

    public function show(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        $receipt->load(['merchant', 'file', 'lineItems', 'tags']);

        return Inertia::render('Receipt/Show', [
            'receipt' => [
                'id' => $receipt->id,
                'merchant' => $receipt->merchant,
                'receipt_date' => $receipt->receipt_date,
                'tax_amount' => $receipt->tax_amount,
                'total_amount' => $receipt->total_amount,
                'currency' => $receipt->currency,
                'receipt_category' => $receipt->receipt_category,
                'receipt_description' => $receipt->receipt_description,
                'file' => $receipt->file ? [
                    'id' => $receipt->file->id,
                    'url' => route('receipts.showImage', $receipt->id),
                    'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null,
                    'extension' => $receipt->file->fileExtension ?? 'jpg',
                    'mime_type' => $receipt->file->mime_type,
                ] : null,
                'lineItems' => $receipt->lineItems ? $receipt->lineItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'text' => $item->text,
                        'sku' => $item->sku,
                        'qty' => $item->qty,
                        'price' => $item->price,
                        'total' => $item->total,
                    ];
                }) : [],
                'tags' => $receipt->tags ? $receipt->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ];
                }) : [],
            ],
        ]);
    }

    public function showImage(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        if (! $receipt->file || ! $receipt->file->guid) {
            abort(404);
        }

        // Use the actual file extension, defaulting to jpg if not set
        $extension = $receipt->file->fileExtension ?? 'jpg';

        return redirect()->route('documents.serve', [
            'guid' => $receipt->file->guid,
            'type' => 'receipts',
            'extension' => $extension,
            'user_id' => $receipt->user_id,
        ]);
    }

    public function showPdf(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        if (! $receipt->file || ! $receipt->file->guid) {
            abort(404);
        }

        $extension = $receipt->file->fileExtension ?? 'jpg';

        if ($extension !== 'pdf') {
            return redirect()->route('receipts.showImage', $receipt->id);
        }

        return redirect()->route('documents.serve', [
            'guid' => $receipt->file->guid,
            'type' => 'receipts',
            'extension' => 'pdf',
            'user_id' => $receipt->user_id,
        ]);
    }

    public function byMerchant($merchant)
    {
        // Validate merchant ID
        $merchantModel = \App\Models\Merchant::findOrFail($merchant);

        // Verify user has access to this merchant through their receipts
        $hasAccess = Receipt::where('merchant_id', $merchantModel->id)
            ->where('user_id', auth()->id())
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Unauthorized access to merchant');
        }

        $receipts = Receipt::where('merchant_id', $merchantModel->id)
            ->where('user_id', auth()->id())
            ->with(['merchant', 'file', 'lineItems'])
            ->orderBy('receipt_date', 'desc')
            ->get()
            ->map(function ($receipt) {
                return [
                    'id' => $receipt->id,
                    'merchant' => $receipt->merchant,
                    'category' => $receipt->category,
                    'category_id' => $receipt->category_id,
                    'receipt_date' => $receipt->receipt_date,
                    'tax_amount' => $receipt->tax_amount,
                    'total_amount' => $receipt->total_amount,
                    'currency' => $receipt->currency,
                    'receipt_category' => $receipt->receipt_category,
                    'receipt_description' => $receipt->receipt_description,
                    'file' => $receipt->file ? [
                        'id' => $receipt->file->id,
                        'url' => $receipt->file ? route('receipts.showImage', $receipt->id) : null,
                        'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null,
                        'extension' => $receipt->file->fileExtension ?? 'jpg',
                        'mime_type' => $receipt->file->mime_type,
                    ] : null,
                    'lineItems' => $receipt->lineItems ? $receipt->lineItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->text,
                            'sku' => $item->sku,
                            'quantity' => $item->qty,
                            'unit_price' => $item->price,
                            'total_amount' => $item->total,
                        ];
                    }) : [],
                ];
            });

        return Inertia::render('Receipt/Index', [
            'receipts' => $receipts,
        ]);
    }

    public function destroy(Receipt $receipt, ReceiptService $receiptService)
    {
        $this->authorize('delete', $receipt);

        if ($receiptService->deleteReceipt($receipt)) {
            return redirect()->route('receipts.index')->with('success', 'The receipt was deleted');
        }

        return redirect()->back()->with('error', 'Could not delete the receipt');
    }

    public function update(Request $request, Receipt $receipt)
    {
        $this->authorize('update', $receipt);

        $validated = $request->validate([
            'receipt_date' => 'required|date',
            'total_amount' => 'required|numeric',
            'tax_amount' => 'nullable|numeric',
            'currency' => 'required|string|size:3',
            'receipt_category' => 'nullable|string|max:255',
            'receipt_description' => 'nullable|string|max:1000',
            'merchant_id' => 'nullable|exists:merchants,id',
            'tags' => 'sometimes|array',
            'tags.*' => 'integer|exists:tags,id',
        ]);

        // Sanitize string inputs
        $validated = $this->sanitizeData($validated, ['receipt_category', 'receipt_description']);

        // Update receipt
        $receipt->update(array_diff_key($validated, ['tags' => '']));

        // Sync tags if provided
        if (isset($validated['tags'])) {
            $receipt->tags()->sync($validated['tags']);
        }

        return redirect()->back()->with('success', 'Receipt updated successfully');
    }

    /**
     * Attach tag to receipt
     */
    public function attachTag(Request $request, Receipt $receipt)
    {
        $this->authorize('update', $receipt);

        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $tag = \App\Models\Tag::findOrCreateByName(
            $validated['name'],
            auth()->id()
        );

        if (! $receipt->tags->contains($tag->id)) {
            $receipt->tags()->attach($tag->id, ['file_type' => 'receipt']);
        }

        return back()->with('success', 'Tag added successfully');
    }

    /**
     * Detach tag from receipt
     */
    public function detachTag(Receipt $receipt, \App\Models\Tag $tag)
    {
        $this->authorize('update', $receipt);
        $this->authorize('view', $tag);

        $receipt->tags()->detach($tag->id);

        return back()->with('success', 'Tag removed successfully');
    }
}
