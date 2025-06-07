<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Services\ReceiptService;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ReceiptController extends Controller
{

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
                        'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null
                    ] : null,
                    'lineItems' => $receipt->lineItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->text,
                            'sku' => $item->sku,
                            'quantity' => $item->qty,
                            'unit_price' => $item->price,
                            'total_amount' => $item->total
                        ];
                    })
                ];
            });

        $categories = auth()->user()->categories()
            ->active()
            ->ordered()
            ->get();

        return Inertia::render('Receipt/Index', [
            'receipts' => $receipts,
            'categories' => $categories
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
                    'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null
                ] : null,
                'lineItems' => $receipt->lineItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'text' => $item->text,
                        'sku' => $item->sku,
                        'qty' => $item->qty,
                        'price' => $item->price,
                        'total' => $item->total
                    ];
                })
            ]
        ]);
    }

    public function showImage(Receipt $receipt)
    {
        $this->authorize('view', $receipt);
        
        if (!$receipt->file || !$receipt->file->guid) {
            abort(404);
        }

        if (!$this->documentService->documentExists($receipt->file->guid, 'receipts', 'jpg')) {
            abort(404, 'Image not found in storage');
        }

        return redirect()->route('documents.url', [
            'guid' => $receipt->file->guid,
            'type' => 'receipts',
            'extension' => 'jpg'
        ]);
    }

    public function showPdf(Receipt $receipt)
    {
        $this->authorize('view', $receipt);
        
        if (!$receipt->file || !$receipt->file->guid) {
            abort(404);
        }

        return redirect()->route('documents.url', [
            'guid' => $receipt->file->guid,
            'type' => 'receipts',
            'extension' => 'pdf'
        ]);
    }

    public function byMerchant($merchant)
    {
        $receipts = Receipt::where('merchant_id', $merchant)
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
                        'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null
                    ] : null,
                    'lineItems' => $receipt->lineItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->text,
                            'sku' => $item->sku,
                            'quantity' => $item->qty,
                            'unit_price' => $item->price,
                            'total_amount' => $item->total
                        ];
                    })
                ];
            });

        return Inertia::render('Receipt/Index', [
            'receipts' => $receipts
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
            'receipt_category' => 'nullable|string',
            'receipt_description' => 'nullable|string',
            'merchant_id' => 'nullable|exists:merchants,id',
        ]);

        $receipt->update($validated);

        return redirect()->back()->with('success', 'Receipt updated successfully');
    }

    public function updateLineItem(Request $request, Receipt $receipt, $lineItemId)
    {
        $this->authorize('update', $receipt);
        
        $validated = $request->validate([
            'text' => 'required|string',
            'sku' => 'nullable|string',
            'qty' => 'required|numeric',
            'price' => 'required|numeric',
            'total' => 'required|numeric',
        ]);

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
            'text' => 'required|string',
            'sku' => 'nullable|string',
            'qty' => 'required|numeric',
            'price' => 'required|numeric',
            'total' => 'required|numeric',
        ]);

        $receipt->lineItems()->create($validated);

        return redirect()->back()->with('success', 'Line item added successfully');
    }
}
