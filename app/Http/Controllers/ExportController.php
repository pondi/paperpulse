<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceiptExportRequest;
use App\Models\Receipt;
use App\Services\ArchiveExportService;
use App\Services\ReceiptExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct()
    {
        // Apply rate limiting middleware to all export methods
        $this->middleware('throttle:exports');
    }

    public function exportCsv(ReceiptExportRequest $request, ReceiptExportService $service): StreamedResponse
    {
        $query = $service->query($request->user()->id, $request->validated());

        return response()->streamDownload(function () use ($service, $query): void {
            $stream = fopen('php://output', 'wb');
            try {
                $service->writeCsv($query, $stream);
            } finally {
                fclose($stream);
            }
        }, 'receipts_'.now()->format('Y-m-d_His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(ReceiptExportRequest $request, ReceiptExportService $service, ArchiveExportService $exports): Response
    {
        $filters = $request->validated();
        $query = $service->query($request->user()->id, $filters);
        $total = $query->count();
        if ($total > config('exports.immediate_limit')) {
            return $exports->queue($request, 'pdf', $filters, $total);
        }

        return $service->pdf($query->get(), $request->user(), $filters)->download('receipts_'.now()->format('Y-m-d_His').'.pdf');
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
