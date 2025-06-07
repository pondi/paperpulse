<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Services\ReceiptService;
use App\Traits\SanitizesInput;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReceiptController extends Controller
{
    use SanitizesInput;

    public function index()
    {
        $receipts = Receipt::with(['merchant', 'file', 'lineItems', 'category'])
            ->where('user_id', auth()->id())
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
                        'url' => route('receipts.showImage', $receipt->id),
                        'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null,
                    ] : null,
                    'lineItems' => $receipt->lineItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->text,
                            'sku' => $item->sku,
                            'quantity' => $item->qty,
                            'unit_price' => $item->price,
                            'total_amount' => $item->total,
                        ];
                    }),
                ];
            });

        $categories = auth()->user()->categories()
            ->active()
            ->ordered()
            ->get();

        return Inertia::render('Receipt/Index', [
            'receipts' => $receipts,
            'categories' => $categories,
        ]);
    }

    public function show(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        $receipt->load(['merchant', 'file', 'lineItems']);

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
                ] : null,
                'lineItems' => $receipt->lineItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'text' => $item->text,
                        'sku' => $item->sku,
                        'qty' => $item->qty,
                        'price' => $item->price,
                        'total' => $item->total,
                    ];
                }),
            ],
        ]);
    }

    public function showImage(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        if (! $receipt->file || ! $receipt->file->guid) {
            abort(404);
        }

        if (! $this->documentService->documentExists($receipt->file->guid, 'receipts', 'jpg')) {
            abort(404, 'Image not found in storage');
        }

        return redirect()->route('documents.url', [
            'guid' => $receipt->file->guid,
            'type' => 'receipts',
            'extension' => 'jpg',
        ]);
    }

    public function showPdf(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        if (! $receipt->file || ! $receipt->file->guid) {
            abort(404);
        }

        return redirect()->route('documents.url', [
            'guid' => $receipt->file->guid,
            'type' => 'receipts',
            'extension' => 'pdf',
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
                    ] : null,
                    'lineItems' => $receipt->lineItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->text,
                            'sku' => $item->sku,
                            'quantity' => $item->qty,
                            'unit_price' => $item->price,
                            'total_amount' => $item->total,
                        ];
                    }),
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
        ]);

        // Sanitize string inputs
        $validated = $this->sanitizeData($validated, ['receipt_category', 'receipt_description']);

        $receipt->update($validated);

        return redirect()->back()->with('success', 'Receipt updated successfully');
    }

    public function updateLineItem(Request $request, Receipt $receipt, $lineItemId)
    {
        $this->authorize('update', $receipt);

        $validated = $request->validate([
            'text' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'qty' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
        ]);

        // Sanitize string inputs
        $validated = $this->sanitizeData($validated, ['text', 'sku']);

        $lineItem = $receipt->lineItems()->findOrFail($lineItemId);
        $lineItem->update($validated);

        return redirect()->back()->with('success', 'Line item updated successfully');
    }

    public function deleteLineItem(Receipt $receipt, $lineItemId)
    {
        $this->authorize('update', $receipt);

        $lineItem = $receipt->lineItems()->findOrFail($lineItemId);
        $lineItem->delete();

        return redirect()->back()->with('success', 'Line item deleted successfully');
    }

    public function addLineItem(Request $request, Receipt $receipt)
    {
        $this->authorize('update', $receipt);

        $validated = $request->validate([
            'text' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'qty' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
        ]);

        // Sanitize string inputs
        $validated = $this->sanitizeData($validated, ['text', 'sku']);

        $receipt->lineItems()->create($validated);

        return redirect()->back()->with('success', 'Line item added successfully');
    }
}
