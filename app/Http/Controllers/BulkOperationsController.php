<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkReceiptIdsRequest;
use App\Models\Category;
use App\Models\Receipt;
use App\Notifications\BulkOperationCompleted;
use App\Rules\ExistsForUser;
use App\Services\ReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class BulkOperationsController extends Controller
{
    /**
     * Delete multiple receipts
     */
    public function bulkDelete(BulkReceiptIdsRequest $request)
    {
        $receiptService = app(ReceiptService::class);
        $deletedCount = 0;

        // Delete receipts owned by the user via ReceiptService to ensure
        // related files/artifacts are cleaned up and don't affect deduplication.
        $receipts = Receipt::whereIn('id', $request->validated()['receipt_ids'])
            ->where('user_id', auth()->id())
            ->with('file')
            ->get();

        foreach ($receipts as $receipt) {
            if ($receiptService->deleteReceipt($receipt)) {
                $deletedCount++;
            }
        }

        // Send notification
        $user = auth()->user();
        if ($user->preferences && $user->preferences->notify_bulk_complete) {
            try {
                $user->notify(new BulkOperationCompleted('delete', $deletedCount));
            } catch (Exception) {
                // Log but don't fail the operation
            }
        }

        return redirect()->back()->with('success',
            trans_choice('Deleted :count receipt|Deleted :count receipts', $deletedCount, ['count' => $deletedCount])
        );
    }

    /**
     * Update category for multiple receipts
     */
    public function bulkCategorize(Request $request)
    {
        $validated = $request->validate([
            'receipt_ids' => 'required|array',
            'receipt_ids.*' => ['integer', new ExistsForUser('receipts')],
            'category_id' => ['nullable', 'integer', new ExistsForUser('categories')],
            'category' => 'nullable|string|max:255',
        ]);

        // Ensure either category_id or category is provided
        if (! ($validated['category_id'] ?? null) && ! ($validated['category'] ?? null)) {
            return redirect()->back()->with('error', 'Please select a category.');
        }

        $data = [];
        if ($validated['category_id'] ?? null) {
            // Verify user owns the category
            $category = Category::find($validated['category_id']);
            $this->authorize('update', $category);
            $data['category_id'] = $validated['category_id'];
        }

        if ($validated['category'] ?? null) {
            $data['receipt_category'] = $validated['category'];
        }

        // Only update receipts owned by the user
        $updatedCount = Receipt::whereIn('id', $validated['receipt_ids'])
            ->where('user_id', auth()->id())
            ->update($data);

        // Send notification
        $user = auth()->user();
        if ($user->preferences && $user->preferences->notify_bulk_complete) {
            try {
                $user->notify(new BulkOperationCompleted('categorize', $updatedCount));
            } catch (Exception) {
                // Log but don't fail the operation
            }
        }

        return redirect()->back()->with('success',
            trans_choice('Categorized :count receipt|Categorized :count receipts', $updatedCount, ['count' => $updatedCount])
        );
    }

    /**
     * Export multiple receipts as CSV
     */
    public function bulkExportCsv(BulkReceiptIdsRequest $request)
    {
        $receiptIds = $request->validated()['receipt_ids'];
        $userId = auth()->id();

        $filename = 'receipts_selection_'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($receiptIds, $userId) {
            $file = fopen('php://output', 'w');

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

            Receipt::with(['merchant', 'lineItems'])
                ->whereIn('id', $receiptIds)
                ->where('user_id', $userId)
                ->orderBy('receipt_date', 'desc')
                ->chunk(200, function ($receipts) use ($file) {
                    foreach ($receipts as $receipt) {
                        fputcsv($file, $this->formatCsvRow($receipt));
                    }
                });

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Export multiple receipts as PDF
     */
    public function bulkExportPdf(BulkReceiptIdsRequest $request)
    {
        $receiptIds = $request->validated()['receipt_ids'];
        $userId = auth()->id();

        $query = Receipt::with(['merchant', 'lineItems'])
            ->whereIn('id', $receiptIds)
            ->where('user_id', $userId)
            ->orderBy('receipt_date', 'desc');

        $aggregates = Receipt::whereIn('id', $receiptIds)
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) as total_count, SUM(total_amount) as total_amount')
            ->first();

        $data = [
            'receipts' => $query->lazy(200),
            'generated_at' => now(),
            'total_amount' => (float) ($aggregates->total_amount ?? 0),
            'total_count' => $aggregates->total_count ?? 0,
        ];

        $pdf = Pdf::loadView('exports.receipts-pdf', $data);

        $filename = 'receipts_selection_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Get bulk operation statistics
     */
    public function getStats(BulkReceiptIdsRequest $request)
    {
        $validated = $request->validated();

        $stats = Receipt::whereIn('id', $validated['receipt_ids'])
            ->where('user_id', auth()->id())
            ->selectRaw('
                COUNT(*) as count,
                SUM(total_amount) as total_amount,
                SUM(tax_amount) as total_tax,
                MIN(receipt_date) as earliest_date,
                MAX(receipt_date) as latest_date
            ')
            ->first();

        $categories = Receipt::whereIn('id', $validated['receipt_ids'])
            ->where('user_id', auth()->id())
            ->select('receipt_category', DB::raw('COUNT(*) as count'))
            ->groupBy('receipt_category')
            ->get()
            ->pluck('count', 'receipt_category')
            ->toArray();

        return response()->json([
            'count' => $stats->count,
            'total_amount' => round($stats->total_amount ?? 0, 2),
            'total_tax' => round($stats->total_tax ?? 0, 2),
            'date_range' => [
                'from' => $stats->earliest_date ? Carbon::parse($stats->earliest_date)->format('Y-m-d') : null,
                'to' => $stats->latest_date ? Carbon::parse($stats->latest_date)->format('Y-m-d') : null,
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function formatCsvRow(Receipt $receipt): array
    {
        $lineItems = $receipt->lineItems->map(function ($item) {
            return $item->text.' (Qty: '.$item->qty.', Price: '.$item->price.')';
        })->implode('; ');

        return [
            $receipt->receipt_date ? Carbon::parse($receipt->receipt_date)->format('Y-m-d') : '',
            $receipt->merchant?->name ?? 'Unknown',
            $receipt->receipt_category ?? '',
            $receipt->receipt_description ?? '',
            $receipt->total_amount ?? 0,
            $receipt->tax_amount ?? 0,
            $receipt->currency ?? '',
            $receipt->lineItems->count(),
            $lineItems,
        ];
    }
}
