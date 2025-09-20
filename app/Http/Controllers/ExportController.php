<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ExportController extends Controller
{
    public function __construct()
    {
        // Apply rate limiting middleware to all export methods
        $this->middleware('throttle:exports');
    }

    /**
     * Export receipts as CSV
     */
    public function exportCsv(Request $request)
    {
        $query = Receipt::with(['merchant', 'lineItems'])
            ->where('user_id', auth()->id());

        // Apply filters
        if ($request->has('from_date')) {
            $query->where('receipt_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('receipt_date', '<=', $request->to_date);
        }

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('category')) {
            $query->where('receipt_category', $request->category);
        }

        $receipts = $query->orderBy('receipt_date', 'desc')->get();

        $filename = 'receipts_'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($receipts) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Receipt Date',
                'Merchant',
                'Category',
                'Description',
                'Total Amount',
                'Tax Amount',
                'Currency',
                'Items Count',
                'Line Items',
            ]);

            foreach ($receipts as $receipt) {
                $lineItems = $receipt->lineItems->map(function ($item) {
                    return $item->text.' (Qty: '.$item->qty.', Price: '.$item->price.')';
                })->implode('; ');

                fputcsv($file, [
                    $receipt->receipt_date ? Carbon::parse($receipt->receipt_date)->format('Y-m-d') : '',
                    $receipt->merchant?->name ?? 'Unknown',
                    $receipt->receipt_category ?? '',
                    $receipt->receipt_description ?? '',
                    $receipt->total_amount ?? 0,
                    $receipt->tax_amount ?? 0,
                    $receipt->currency ?? auth()->user()->preference('currency', 'NOK'),
                    $receipt->lineItems->count(),
                    $lineItems,
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Export receipts as PDF
     */
    public function exportPdf(Request $request)
    {
        $query = Receipt::with(['merchant', 'lineItems'])
            ->where('user_id', auth()->id());

        // Apply filters
        if ($request->has('from_date')) {
            $query->where('receipt_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('receipt_date', '<=', $request->to_date);
        }

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('category')) {
            $query->where('receipt_category', $request->category);
        }

        $receipts = $query->orderBy('receipt_date', 'desc')->get();

        $data = [
            'receipts' => $receipts,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'generated_at' => now(),
            'total_amount' => $receipts->sum('total_amount'),
            'total_count' => $receipts->count(),
        ];

        $pdf = Pdf::loadView('exports.receipts-pdf', $data);

        $filename = 'receipts_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export single receipt as PDF
     */
    public function exportReceiptPdf($id)
    {
        $receipt = Receipt::with(['merchant', 'lineItems', 'file'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $data = [
            'receipt' => $receipt,
            'generated_at' => now(),
        ];

        $pdf = Pdf::loadView('exports.receipt-single-pdf', $data);

        $filename = 'receipt_'.$receipt->id.'_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->download($filename);
    }
}
