<?php

namespace App\Http\Controllers;

use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Voucher;
use App\Models\Warranty;
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
        $period = $request->get('period', 'all');
        $tab = $request->get('tab', 'overview');
        $startDate = $this->getStartDate($period);
        $endDate = Carbon::now();

        $overviewCounts = $this->getOverviewCounts($userId);

        $tabData = match ($tab) {
            'receipts' => $this->getReceiptData($userId, $startDate, $endDate),
            'invoices' => $this->getInvoiceData($userId, $startDate, $endDate),
            'banking' => $this->getBankingData($userId, $startDate, $endDate),
            'contracts' => $this->getContractData($userId, $startDate, $endDate),
            'documents' => $this->getDocumentData($userId, $startDate, $endDate),
            default => $this->getOverviewData($userId, $startDate, $endDate),
        };

        return Inertia::render('Analytics/Dashboard', [
            'overview_counts' => $overviewCounts,
            'tab_data' => $tabData,
            'current_period' => $period,
            'current_tab' => $tab,
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

    private function getOverviewCounts(int $userId): array
    {
        return [
            'receipts' => Receipt::where('user_id', $userId)->count(),
            'invoices' => Invoice::where('user_id', $userId)->count(),
            'contracts' => Contract::where('user_id', $userId)->count(),
            'bank_statements' => BankStatement::where('user_id', $userId)->count(),
            'documents' => Document::where('user_id', $userId)->count(),
            'vouchers' => Voucher::where('user_id', $userId)->count(),
            'warranties' => Warranty::where('user_id', $userId)->count(),
        ];
    }

    private function getOverviewData(int $userId, ?Carbon $startDate, Carbon $endDate): array
    {
        $receiptTotal = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
            ->sum('total_amount');

        $invoiceTotal = Invoice::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate]))
            ->sum('total_amount');

        $contractTotal = Contract::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('effective_date', [$startDate, $endDate]))
            ->sum('contract_value');

        // Expiring within next 30 days
        $expiringVouchers = Voucher::where('user_id', $userId)
            ->where('is_redeemed', false)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->count();

        $expiringWarranties = Warranty::where('user_id', $userId)
            ->whereNotNull('warranty_end_date')
            ->whereBetween('warranty_end_date', [now(), now()->addDays(30)])
            ->count();

        $expiringContracts = Contract::where('user_id', $userId)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->count();

        // Combined monthly spending trend (receipts + invoices)
        $receiptMonthExpr = $this->getMonthExpression('receipt_date');
        $receiptTrend = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
            ->whereNotNull('receipt_date')
            ->select(DB::raw("{$receiptMonthExpr} as month"), DB::raw('SUM(total_amount) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        $invoiceMonthExpr = $this->getMonthExpression('invoice_date');
        $invoiceTrend = Invoice::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate]))
            ->whereNotNull('invoice_date')
            ->select(DB::raw("{$invoiceMonthExpr} as month"), DB::raw('SUM(total_amount) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        $allMonths = $receiptTrend->keys()->merge($invoiceTrend->keys())->unique()->sort();
        $combinedTrend = $allMonths->map(fn ($month) => [
            'month' => Carbon::parse($month.'-01')->format('M Y'),
            'receipts' => round((float) ($receiptTrend[$month] ?? 0), 2),
            'invoices' => round((float) ($invoiceTrend[$month] ?? 0), 2),
            'total' => round((float) ($receiptTrend[$month] ?? 0) + (float) ($invoiceTrend[$month] ?? 0), 2),
        ])->values();

        return [
            'financial_totals' => [
                'receipts' => round((float) $receiptTotal, 2),
                'invoices' => round((float) $invoiceTotal, 2),
                'contracts' => round((float) $contractTotal, 2),
            ],
            'expiring_soon' => [
                'vouchers' => $expiringVouchers,
                'warranties' => $expiringWarranties,
                'contracts' => $expiringContracts,
            ],
            'monthly_trend' => $combinedTrend,
        ];
    }

    private function getReceiptData(int $userId, ?Carbon $startDate, Carbon $endDate): array
    {
        $count = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
            ->count();

        $total = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
            ->sum('total_amount');

        $tax = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
            ->sum('tax_amount');

        $merchantCount = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
            ->distinct('merchant_id')
            ->count('merchant_id');

        $spendingByCategory = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
            ->select('receipt_category', DB::raw('SUM(total_amount) as total'))
            ->groupBy('receipt_category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($item) => [
                'category' => $item->receipt_category ?: 'Uncategorized',
                'total' => (float) $item->total,
            ]);

        $topMerchants = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
            ->select('merchant_id', DB::raw('COUNT(*) as receipt_count'), DB::raw('SUM(total_amount) as total'))
            ->with('merchant')
            ->groupBy('merchant_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'merchant' => $item->merchant?->name ?: 'Unknown',
                'receipt_count' => $item->receipt_count,
                'total' => (float) $item->total,
            ]);

        $monthExpr = $this->getMonthExpression('receipt_date');
        $monthlyTrend = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
            ->whereNotNull('receipt_date')
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw('COUNT(*) as receipt_count'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($item) => [
                'month' => Carbon::parse($item->month.'-01')->format('M Y'),
                'receipt_count' => $item->receipt_count,
                'total' => (float) $item->total,
            ]);

        $dowExpr = $this->getDayOfWeekExpression('receipt_date');
        $dayOfWeek = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
            ->whereNotNull('receipt_date')
            ->select(DB::raw("{$dowExpr} as dow"), DB::raw('SUM(total_amount) as total'))
            ->groupBy('dow')
            ->get()
            ->mapWithKeys(fn ($item) => [(int) $item->dow => round((float) $item->total, 2)]);

        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $dayOfWeekData = collect($dayNames)->map(fn ($name, $index) => [
            'day' => $name,
            'total' => $dayOfWeek[$index] ?? 0,
        ])->values();

        $recentReceipts = Receipt::where('user_id', $userId)
            ->with('merchant')
            ->orderBy('receipt_date', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($receipt) => [
                'id' => $receipt->id,
                'merchant' => $receipt->merchant?->name ?: 'Unknown',
                'date' => $receipt->receipt_date ? Carbon::parse($receipt->receipt_date)->format('Y-m-d') : null,
                'total' => $receipt->total_amount,
                'category' => $receipt->receipt_category ?: 'Uncategorized',
            ]);

        return [
            'stats' => [
                'count' => $count,
                'total' => round((float) $total, 2),
                'avg' => $count > 0 ? round((float) $total / $count, 2) : 0,
                'tax' => round((float) $tax, 2),
                'merchants' => $merchantCount,
            ],
            'spending_by_category' => $spendingByCategory,
            'top_merchants' => $topMerchants,
            'monthly_trend' => $monthlyTrend,
            'day_of_week' => $dayOfWeekData,
            'recent_receipts' => $recentReceipts,
        ];
    }

    private function getInvoiceData(int $userId, ?Carbon $startDate, Carbon $endDate): array
    {
        $baseQuery = Invoice::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate]));

        $count = (clone $baseQuery)->count();
        $total = (clone $baseQuery)->sum('total_amount');
        $avg = $count > 0 ? round((float) $total / $count, 2) : 0;

        $recipientCount = (clone $baseQuery)
            ->whereNotNull('to_name')
            ->distinct('to_name')
            ->count('to_name');

        $topRecipients = Invoice::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate]))
            ->whereNotNull('to_name')
            ->select('to_name', DB::raw('COUNT(*) as invoice_count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('to_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'recipient' => $item->to_name,
                'invoice_count' => $item->invoice_count,
                'total' => round((float) $item->total, 2),
            ]);

        $topVendors = Invoice::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate]))
            ->select('merchant_id', DB::raw('COUNT(*) as invoice_count'), DB::raw('SUM(total_amount) as total'))
            ->with('merchant')
            ->whereNotNull('merchant_id')
            ->groupBy('merchant_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'vendor' => $item->merchant?->name ?: 'Unknown',
                'invoice_count' => $item->invoice_count,
                'total' => (float) $item->total,
            ]);

        $monthExpr = $this->getMonthExpression('invoice_date');
        $monthlyTrend = Invoice::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate]))
            ->whereNotNull('invoice_date')
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($item) => [
                'month' => Carbon::parse($item->month.'-01')->format('M Y'),
                'invoice_count' => $item->invoice_count,
                'total' => (float) $item->total,
            ]);

        return [
            'stats' => [
                'count' => $count,
                'total' => round((float) $total, 2),
                'avg' => $avg,
                'recipient_count' => $recipientCount,
            ],
            'top_recipients' => $topRecipients,
            'top_vendors' => $topVendors,
            'monthly_trend' => $monthlyTrend,
        ];
    }

    private function getBankingData(int $userId, ?Carbon $startDate, Carbon $endDate): array
    {
        $statementCount = BankStatement::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('statement_date', [$startDate, $endDate]))
            ->count();

        $statementIds = BankStatement::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('statement_date', [$startDate, $endDate]))
            ->pluck('id');

        $transactionCount = BankTransaction::whereIn('bank_statement_id', $statementIds)->count();

        $totalCredits = BankStatement::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('statement_date', [$startDate, $endDate]))
            ->sum('total_credits');

        $totalDebits = BankStatement::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('statement_date', [$startDate, $endDate]))
            ->sum('total_debits');

        $balanceTrend = BankStatement::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('statement_date', [$startDate, $endDate]))
            ->whereNotNull('statement_date')
            ->select('statement_date', 'opening_balance', 'closing_balance', 'bank_name')
            ->orderBy('statement_date')
            ->get()
            ->map(fn ($item) => [
                'date' => Carbon::parse($item->statement_date)->format('M Y'),
                'opening' => (float) $item->opening_balance,
                'closing' => (float) $item->closing_balance,
                'bank' => $item->bank_name,
            ]);

        $spendingByCategory = BankTransaction::whereIn('bank_statement_id', $statementIds)
            ->where('transaction_type', 'debit')
            ->whereNotNull('category')
            ->select('category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category,
                'total' => (float) $item->total,
                'count' => $item->count,
            ]);

        $topCounterparties = BankTransaction::whereIn('bank_statement_id', $statementIds)
            ->whereNotNull('counterparty_name')
            ->select('counterparty_name', DB::raw('COUNT(*) as transaction_count'), DB::raw('SUM(amount) as total'))
            ->groupBy('counterparty_name')
            ->orderByDesc('transaction_count')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'name' => $item->counterparty_name,
                'transaction_count' => $item->transaction_count,
                'total' => (float) $item->total,
            ]);

        return [
            'stats' => [
                'statement_count' => $statementCount,
                'transaction_count' => $transactionCount,
                'total_credits' => round((float) $totalCredits, 2),
                'total_debits' => round((float) $totalDebits, 2),
                'net_flow' => round((float) $totalCredits - (float) $totalDebits, 2),
            ],
            'balance_trend' => $balanceTrend,
            'spending_by_category' => $spendingByCategory,
            'top_counterparties' => $topCounterparties,
        ];
    }

    private function getContractData(int $userId, ?Carbon $startDate, Carbon $endDate): array
    {
        $total = Contract::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('effective_date', [$startDate, $endDate]))
            ->count();

        $active = Contract::where('user_id', $userId)
            ->where('status', 'active')
            ->count();

        $expired = Contract::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('status', 'expired')
                    ->orWhere(fn ($q2) => $q2->whereNotNull('expiry_date')->where('expiry_date', '<', now()));
            })
            ->count();

        $totalValue = Contract::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('effective_date', [$startDate, $endDate]))
            ->sum('contract_value');

        $statusBreakdown = Contract::where('user_id', $userId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => [
                'status' => $item->status ?: 'Unknown',
                'count' => $item->count,
            ]);

        $typeDistribution = Contract::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('effective_date', [$startDate, $endDate]))
            ->select('contract_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(contract_value) as value'))
            ->groupBy('contract_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($item) => [
                'type' => $item->contract_type ?: 'Other',
                'count' => $item->count,
                'value' => round((float) $item->value, 2),
            ]);

        $expiringSoon = Contract::where('user_id', $userId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', now()->addDays(90))
            ->orderBy('expiry_date')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->contract_title ?: 'Untitled',
                'type' => $item->contract_type ?: 'Other',
                'expiry_date' => Carbon::parse($item->expiry_date)->format('Y-m-d'),
                'value' => (float) $item->contract_value,
                'days_until_expiry' => (int) now()->diffInDays($item->expiry_date),
            ]);

        return [
            'stats' => [
                'total' => $total,
                'active' => $active,
                'expired' => $expired,
                'total_value' => round((float) $totalValue, 2),
            ],
            'status_breakdown' => $statusBreakdown,
            'type_distribution' => $typeDistribution,
            'expiring_soon' => $expiringSoon,
        ];
    }

    private function getDocumentData(int $userId, ?Carbon $startDate, Carbon $endDate): array
    {
        $total = Document::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->count();

        $totalPages = Document::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->sum('page_count');

        $typeDistribution = Document::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->select('document_type', DB::raw('COUNT(*) as count'))
            ->groupBy('document_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($item) => [
                'type' => $item->document_type ?: 'Other',
                'count' => $item->count,
            ]);

        $monthExpr = $this->getMonthExpression('created_at');
        $monthlyTrend = Document::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($item) => [
                'month' => Carbon::parse($item->month.'-01')->format('M Y'),
                'count' => $item->count,
            ]);

        return [
            'stats' => [
                'total' => $total,
                'total_pages' => (int) $totalPages,
            ],
            'type_distribution' => $typeDistribution,
            'monthly_trend' => $monthlyTrend,
        ];
    }

    private function getStartDate(string $period): ?Carbon
    {
        return match ($period) {
            'month' => Carbon::now()->subMonth(),
            'quarter' => Carbon::now()->subQuarter(),
            'year' => Carbon::now()->subYear(),
            'all' => null,
            default => null,
        };
    }

    private function getMonthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "TO_CHAR({$column}, 'YYYY-MM')";
    }

    private function getDayOfWeekExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%w', {$column}) AS INTEGER)"
            : "EXTRACT(DOW FROM {$column})";
    }
}
