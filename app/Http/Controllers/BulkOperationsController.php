<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkCategoryRequest;
use App\Http\Requests\BulkReceiptIdsRequest;
use App\Jobs\Search\ReindexFile;
use App\Models\Category;
use App\Models\Receipt;
use App\Notifications\BulkOperationCompleted;
use App\Services\ArchiveExportService;
use App\Services\ReceiptExportService;
use App\Services\ReceiptService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    public function bulkCategorize(BulkCategoryRequest $request)
    {
        $validated = $request->validated();
        $category = isset($validated['category_id'])
            ? Category::findOrFail($validated['category_id'])
            : Category::where('user_id', auth()->id())->where('name', $validated['category'])->firstOrFail();
        $this->authorize('update', $category);
        $data = ['category_id' => $category->id, 'receipt_category' => $category->name];
        $updatedCount = (new Receipt)->getConnection()->transaction(function () use ($validated, $data): int {
            $receipts = Receipt::whereIn('id', $validated['receipt_ids'])->where('user_id', auth()->id());
            $fileIds = (clone $receipts)->distinct()->pluck('file_id');
            $count = $receipts->update($data);
            foreach ($fileIds as $fileId) {
                ReindexFile::forFile($fileId);
            }

            return $count;
        });

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

    public function bulkExportCsv(BulkReceiptIdsRequest $request, ReceiptExportService $service): StreamedResponse
    {
        $query = $service->query($request->user()->id, $request->validated());

        return response()->streamDownload(function () use ($service, $query): void {
            $stream = fopen('php://output', 'wb');
            try {
                $service->writeCsv($query, $stream, includeNote: false);
            } finally {
                fclose($stream);
            }
        }, 'receipts_selection_'.now()->format('Y-m-d_His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function bulkExportPdf(BulkReceiptIdsRequest $request, ReceiptExportService $service, ArchiveExportService $exports): Response
    {
        $filters = $request->validated();
        $query = $service->query($request->user()->id, $filters);
        $total = $query->count();
        if ($total > config('exports.immediate_limit')) {
            return $exports->queue($request, 'pdf', $filters, $total);
        }

        return $service->pdf($query->get(), $request->user(), $filters)->download('receipts_selection_'.now()->format('Y-m-d_His').'.pdf');
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
}
