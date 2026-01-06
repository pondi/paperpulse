<?php

namespace App\Http\Controllers;

use App\Models\LineItem;
use App\Models\Receipt;
use App\Services\Analytics\ProcessingAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $period = $request->get('period', 'month'); // month, quarter, year

        $startDate = $this->getStartDate($period);
        $endDate = Carbon::now();

        // Overall statistics
        $totalReceipts = Receipt::where('user_id', $userId)->count();
        $totalAmount = Receipt::where('user_id', $userId)->sum('total_amount');
        $totalTax = Receipt::where('user_id', $userId)->sum('tax_amount');
        $totalMerchants = Receipt::where('user_id', $userId)
            ->distinct('merchant_id')
            ->count('merchant_id');

        // Period statistics
        $periodReceipts = Receipt::where('user_id', $userId)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->count();

        $periodAmount = Receipt::where('user_id', $userId)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->sum('total_amount');

        // Spending by category
        $spendingByCategory = Receipt::where('user_id', $userId)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->select('receipt_category', DB::raw('SUM(total_amount) as total'))
            ->groupBy('receipt_category')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                /** @var object $item */
                return [
                    'category' => $item->receipt_category ?: 'Uncategorized',
                    'total' => (float) $item->total,
                ];
            });

        // Top merchants
        $topMerchants = Receipt::where('user_id', $userId)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->select('merchant_id', DB::raw('COUNT(*) as receipt_count'), DB::raw('SUM(total_amount) as total'))
            ->with('merchant')
            ->groupBy('merchant_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                /** @var Receipt $item */
                return [
                    'merchant' => $item->merchant?->name ?: 'Unknown',
                    'receipt_count' => $item->receipt_count,
                    'total' => (float) $item->total,
                ];
            });

        // Monthly spending trend
        $monthlyTrend = Receipt::where('user_id', $userId)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->select(
                DB::raw("TO_CHAR(receipt_date, 'YYYY-MM') as month"),
                DB::raw('COUNT(*) as receipt_count'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                /** @var object $item */
                return [
                    'month' => Carbon::parse($item->month.'-01')->format('M Y'),
                    'receipt_count' => $item->receipt_count,
                    'total' => (float) $item->total,
                ];
            });

        // Recent receipts
        $recentReceipts = Receipt::where('user_id', $userId)
            ->with('merchant')
            ->orderBy('receipt_date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($receipt) {
                return [
                    'id' => $receipt->id,
                    'merchant' => $receipt->merchant?->name ?: 'Unknown',
                    'date' => $receipt->receipt_date ? Carbon::parse($receipt->receipt_date)->format('Y-m-d') : null,
                    'total' => $receipt->total_amount,
                    'category' => $receipt->receipt_category ?: 'Uncategorized',
                ];
            });

        // Average receipt value
        $avgReceiptValue = $periodReceipts > 0 ? $periodAmount / $periodReceipts : 0;

        // Most purchased items
        $topItems = LineItem::whereHas('receipt', function ($query) use ($userId, $startDate, $endDate) {
            $query->where('user_id', $userId)
                ->whereBetween('receipt_date', [$startDate, $endDate]);
        })
            ->select('text', DB::raw('SUM(qty) as total_qty'), DB::raw('COUNT(*) as purchase_count'))
            ->groupBy('text')
            ->orderByDesc('purchase_count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->text,
                    'quantity' => $item->total_qty,
                    'purchases' => $item->purchase_count,
                ];
            });

        return Inertia::render('Analytics/Dashboard', [
            'stats' => [
                'total_receipts' => $totalReceipts,
                'total_amount' => round($totalAmount, 2),
                'total_tax' => round($totalTax, 2),
                'total_merchants' => $totalMerchants,
                'period_receipts' => $periodReceipts,
                'period_amount' => round($periodAmount, 2),
                'avg_receipt_value' => round($avgReceiptValue, 2),
            ],
            'charts' => [
                'spending_by_category' => $spendingByCategory,
                'top_merchants' => $topMerchants,
                'monthly_trend' => $monthlyTrend,
                'top_items' => $topItems,
            ],
            'recent_receipts' => $recentReceipts,
            'current_period' => $period,
        ]);
    }

    /**
     * Admin-only dashboard for AI processing analytics.
     *
     * Displays production learning data: unknown types, low confidence,
     * validation failures, and extraction quality metrics.
     */
    public function processing(Request $request, ProcessingAnalyticsService $analytics)
    {
        // Ensure user is admin
        abort_unless(auth()->user()?->isAdmin(), 403, 'Admin access required');

        $days = $request->get('days', 7);

        // Get all analytics data
        $documentTypes = $analytics->getDocumentTypeDistribution();
        $unknownTypes = $analytics->findUnknownDocumentTypes(20);
        $lowConfidence = $analytics->findLowConfidenceClassifications(0.7, 30);
        $validationFailures = $analytics->findValidationFailuresByType(20);
        $failureDistribution = $analytics->getFailureDistribution();
        $timeline = $analytics->getProcessingTimeline($days);

        // Get quality metrics for each document type
        $qualityMetrics = [];
        foreach ($documentTypes as $type) {
            if ($type->document_type) {
                $qualityMetrics[$type->document_type] = $analytics->getExtractionQualityMetrics($type->document_type);
            }
        }

        return Inertia::render('Analytics/Processing', [
            'stats' => [
                'total_processed' => $documentTypes->sum('total_count'),
                'total_success' => $documentTypes->sum('success_count'),
                'total_failed' => $documentTypes->sum('failure_count'),
                'success_rate' => $documentTypes->sum('total_count') > 0
                    ? round(($documentTypes->sum('success_count') / $documentTypes->sum('total_count')) * 100, 2)
                    : 0,
                'avg_confidence' => round($documentTypes->avg('avg_confidence') ?? 0, 4),
                'avg_duration_ms' => round($documentTypes->avg('avg_duration_ms') ?? 0),
            ],
            'documentTypes' => $documentTypes->map(function ($item) {
                return [
                    'type' => $item->document_type,
                    'total' => $item->total_count,
                    'success' => $item->success_count,
                    'failed' => $item->failure_count,
                    'success_rate' => $item->total_count > 0
                        ? round(($item->success_count / $item->total_count) * 100, 2)
                        : 0,
                    'avg_confidence' => round($item->avg_confidence ?? 0, 4),
                    'avg_extraction_confidence' => round($item->avg_extraction_confidence ?? 0, 4),
                    'avg_duration_ms' => round($item->avg_duration_ms ?? 0),
                ];
            }),
            'qualityMetrics' => $qualityMetrics,
            'unknownTypes' => $unknownTypes->map(function ($item) {
                return [
                    'reasoning' => $item->classification_reasoning,
                    'count' => $item->count,
                    'first_seen' => $item->first_seen,
                    'last_seen' => $item->last_seen,
                ];
            }),
            'lowConfidence' => $lowConfidence->map(function ($item) {
                return [
                    'file_id' => $item->file_id,
                    'filename' => $item->file?->filename ?? 'N/A',
                    'document_type' => $item->document_type,
                    'confidence' => round($item->classification_confidence, 4),
                    'reasoning' => $item->classification_reasoning,
                    'date' => $item->created_at->format('Y-m-d H:i'),
                ];
            }),
            'validationFailures' => $validationFailures->map(function ($item) {
                return [
                    'type' => $item->document_type,
                    'failure_count' => $item->failure_count,
                    'avg_confidence' => round($item->avg_classification_confidence ?? 0, 4),
                    'first_failure' => $item->first_failure,
                    'last_failure' => $item->last_failure,
                ];
            }),
            'failureDistribution' => $failureDistribution->map(function ($item) {
                return [
                    'category' => $item->failure_category,
                    'count' => $item->count,
                    'retryable_count' => $item->retryable_count,
                    'first_seen' => $item->first_seen,
                    'last_seen' => $item->last_seen,
                ];
            }),
            'timeline' => $timeline->map(function ($item) {
                return [
                    'date' => $item->date,
                    'total' => $item->total_count,
                    'success' => $item->success_count,
                    'failed' => $item->failure_count,
                    'success_rate' => $item->total_count > 0
                        ? round(($item->success_count / $item->total_count) * 100, 2)
                        : 0,
                    'avg_duration_ms' => round($item->avg_duration_ms ?? 0),
                ];
            }),
            'current_days' => $days,
        ]);
    }

    private function getStartDate($period)
    {
        switch ($period) {
            case 'month':
                return Carbon::now()->subMonth();
            case 'quarter':
                return Carbon::now()->subQuarter();
            case 'year':
                return Carbon::now()->subYear();
            default:
                return Carbon::now()->subMonth();
        }
    }
}
