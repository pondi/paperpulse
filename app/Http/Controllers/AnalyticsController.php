<?php

namespace App\Http\Controllers;

use App\Enums\TransactionCategory;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\Analytics\ProcessingAnalyticsService;
use App\Services\MonetarySummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function __construct(private MonetarySummaryService $summaries) {}

    public function index(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'all');
        $tab = $request->input('tab', 'overview');
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
            'summary_currency' => $request->user()->preference('currency', 'NOK'),
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

        $days = $request->input('days', 7);

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
        $currency = auth()->user()->preference('currency', 'NOK');
        $receiptQuery = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]));
        $invoiceQuery = Invoice::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate]));
        $receiptSummary = $this->summaries->aggregate($receiptQuery->lazyById(200), ['total' => 'total_amount'], 'receipt_date', $currency,
            ['month' => fn ($row) => $row->receipt_date?->format('Y-m') ?? 'Undated']);
        $invoiceSummary = $this->summaries->aggregate($invoiceQuery->lazyById(200), ['total' => 'total_amount'], 'invoice_date', $currency);
        $invoiceTrendQuery = (clone $invoiceQuery)->whereNotIn('file_id', Receipt::where('user_id', $userId)->whereNotNull('file_id')->select('file_id'));
        $invoiceTrend = $this->summaries->aggregate($invoiceTrendQuery->lazyById(200), ['total' => 'total_amount'], 'invoice_date', $currency,
            ['month' => fn ($row) => $row->invoice_date?->format('Y-m') ?? 'Undated']);
        $contractSummary = $this->summaries->aggregate(Contract::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('effective_date', [$startDate, $endDate]))->lazyById(200),
            ['total' => 'contract_value'], 'effective_date', $currency);

        $expiryStart = auth()->user()->currentDate();
        $expiryWindow = [$expiryStart->toDateString(), $expiryStart->copy()->addDays(30)->toDateString()];
        $expiringVouchers = Voucher::where('user_id', $userId)
            ->where('is_redeemed', false)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', $expiryWindow)
            ->count();

        $expiringWarranties = Warranty::where('user_id', $userId)
            ->whereNotNull('warranty_end_date')
            ->whereBetween('warranty_end_date', $expiryWindow)
            ->count();

        $expiringContracts = Contract::where('user_id', $userId)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', $expiryWindow)
            ->count();

        $receiptTrend = collect($receiptSummary['groups']['month']);
        $invoiceTrend = collect($invoiceTrend['groups']['month']);
        $allMonths = $receiptTrend->keys()->merge($invoiceTrend->keys())->unique()->sort();
        $combinedTrend = $allMonths->map(fn ($month) => [
            'month' => $month === 'Undated' ? $month : Carbon::parse($month.'-01')->format('M Y'),
            'receipts' => $receiptTrend[$month]['total'] ?? (isset($receiptTrend[$month]) ? null : 0),
            'invoices' => $invoiceTrend[$month]['total'] ?? (isset($invoiceTrend[$month]) ? null : 0),
            'total' => MonetarySummaryService::add($receiptTrend[$month]['total'] ?? (isset($receiptTrend[$month]) ? null : 0), $invoiceTrend[$month]['total'] ?? (isset($invoiceTrend[$month]) ? null : 0)),
        ])->values();

        return [
            'financial_totals' => [
                'receipts' => $receiptSummary['amounts']['total'],
                'invoices' => $invoiceSummary['amounts']['total'],
                'contracts' => $contractSummary['amounts']['total'],
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
        $query = Receipt::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]));
        $summary = $this->summaries->aggregate((clone $query)->with('merchant')->lazyById(200), ['total' => 'total_amount', 'tax' => 'tax_amount'], 'receipt_date', auth()->user()->preference('currency', 'NOK'), [
            'category' => fn ($row) => $row->receipt_category ?: 'Uncategorized',
            'merchant' => fn ($row) => $row->merchant?->name ?: 'Unknown',
            'month' => fn ($row) => $row->receipt_date?->format('Y-m') ?? 'Undated',
            'day' => fn ($row) => $row->receipt_date?->format('D') ?? 'Undated',
        ]);
        $total = $summary['amounts']['total'];

        return [
            'stats' => [
                'count' => $summary['count'], 'total' => $total,
                'avg' => $summary['count'] > 0 && $total !== null ? $total / $summary['count'] : ($summary['count'] === 0 ? 0 : null),
                'tax' => $summary['amounts']['tax'],
                'merchants' => (clone $query)->distinct()->count('merchant_id'),
            ],
            'spending_by_category' => collect($summary['groups']['category'])->map(fn ($item, $category) => ['category' => $category, 'total' => $item['total']])->sortByDesc('total')->values(),
            'top_merchants' => collect($summary['groups']['merchant'])->map(fn ($item, $merchant) => ['merchant' => $merchant, 'receipt_count' => $item['count'], 'total' => $item['total']])->sortByDesc('total')->take(10)->values(),
            'monthly_trend' => collect($summary['groups']['month'])->sortKeys()->map(fn ($item, $month) => ['month' => $month === 'Undated' ? $month : Carbon::parse($month.'-01')->format('M Y'), 'receipt_count' => $item['count'], 'total' => $item['total']])->values(),
            'day_of_week' => collect(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'])->map(fn ($day) => ['day' => $day, 'total' => array_key_exists($day, $summary['groups']['day']) ? $summary['groups']['day'][$day]['total'] : 0]),
            'recent_receipts' => (clone $query)->with('merchant')->orderByDesc('receipt_date')->limit(5)->get()->map(fn ($receipt) => [
                'id' => $receipt->id, 'merchant' => $receipt->merchant?->name ?: 'Unknown',
                'date' => $receipt->receipt_date?->format('Y-m-d'), 'total' => $receipt->total_amount, 'currency' => $receipt->currency,
                'category' => $receipt->receipt_category ?: 'Uncategorized',
            ]),
        ];
    }

    private function getInvoiceData(int $userId, ?Carbon $startDate, Carbon $endDate): array
    {
        $query = Invoice::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate]));
        $summary = $this->summaries->aggregate((clone $query)->with('merchant')->lazyById(200), ['total' => 'total_amount'], 'invoice_date', auth()->user()->preference('currency', 'NOK'), [
            'recipient' => fn ($row) => $row->to_name ?? 'Unknown',
            'vendor' => fn ($row) => $row->merchant?->name ?? 'Unknown',
            'month' => fn ($row) => $row->invoice_date?->format('Y-m') ?? 'Undated',
        ]);
        $total = $summary['amounts']['total'];

        return [
            'stats' => [
                'count' => $summary['count'], 'total' => $total,
                'avg' => $summary['count'] > 0 && $total !== null ? $total / $summary['count'] : ($summary['count'] === 0 ? 0 : null),
                'recipient_count' => (clone $query)->distinct()->count('to_name'),
            ],
            'top_recipients' => collect($summary['groups']['recipient'])->map(fn ($item, $name) => ['recipient' => $name, 'invoice_count' => $item['count'], 'total' => $item['total']])->sortByDesc('total')->take(10)->values(),
            'top_vendors' => collect($summary['groups']['vendor'])->map(fn ($item, $name) => ['vendor' => $name, 'invoice_count' => $item['count'], 'total' => $item['total']])->sortByDesc('total')->take(10)->values(),
            'monthly_trend' => collect($summary['groups']['month'])->sortKeys()->map(fn ($item, $month) => ['month' => $month === 'Undated' ? $month : Carbon::parse($month.'-01')->format('M Y'), 'invoice_count' => $item['count'], 'total' => $item['total']])->values(),
        ];
    }

    private function getBankingData(int $userId, ?Carbon $startDate, Carbon $endDate): array
    {
        $transactions = BankTransaction::query()->where('user_id', $userId)
            ->whereHas('bankStatement', fn ($query) => $query->where('user_id', $userId))
            ->whereNotNull('transaction_date')
            ->whereDate('transaction_date', '<=', $endDate->toDateString())
            ->when($startDate, fn ($query) => $query->whereDate('transaction_date', '>=', $startDate->toDateString()));

        $statementCount = (clone $transactions)->distinct()->count('bank_statement_id');
        $transactionCount = (clone $transactions)->count();
        $currency = auth()->user()->preference('currency', 'NOK');
        $credits = $this->summaries->aggregate((clone $transactions)->where('amount', '>', 0)->lazyById(200), ['total' => 'amount'], 'transaction_date', $currency);
        $debits = $this->summaries->aggregate((clone $transactions)->where('amount', '<', 0)->lazyById(200), ['total' => 'amount'], 'transaction_date', $currency, [
            'category' => fn ($row) => ($row->category_group?->value ?? '').'|'.($row->subcategory ?? ''),
            'counterparty' => fn ($row) => $row->counterparty_name ?? 'Unknown',
        ]);
        $totalCredits = $credits['amounts']['total'];
        $totalDebits = $debits['amounts']['total'] === null ? null : -$debits['amounts']['total'];
        $balanceTrend = (clone $transactions)->whereNotNull('balance_after')->with('bankStatement:id,bank_name')
            ->orderBy('transaction_date')->orderBy('id')->get()->map(fn (BankTransaction $item): array => [
                'date' => $item->transaction_date->format('Y-m-d'),
                'opening' => round((float) $item->balance_after - (float) $item->amount, 2),
                'closing' => (float) $item->balance_after, 'currency' => $item->currency,
                'bank' => $item->bankStatement->bank_name,
            ]);
        $spendingByCategory = collect($debits['groups']['category'])->map(function ($item, $key): array {
            [$group, $subcategory] = explode('|', $key, 2);

            return [
                'category' => $group ? TransactionCategory::from($group)->label() : 'Uncategorized',
                'category_group' => $group ?: null, 'subcategory' => $subcategory ?: null,
                'total' => $item['total'] === null ? null : -$item['total'], 'count' => $item['count'],
            ];
        })->sortByDesc('total')->take(10)->values();
        $topCounterparties = collect($debits['groups']['counterparty'])->map(fn ($item, $name) => [
            'name' => $name, 'transaction_count' => $item['count'], 'total' => $item['total'] === null ? null : -$item['total'],
        ])->sortByDesc('total')->take(10)->values();

        return [
            'stats' => [
                'statement_count' => $statementCount,
                'transaction_count' => $transactionCount,
                'total_credits' => $totalCredits,
                'total_debits' => $totalDebits,
                'net_flow' => $totalCredits === null || $totalDebits === null ? null : $totalCredits - $totalDebits,
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

        $summary = $this->summaries->aggregate(Contract::where('user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereBetween('effective_date', [$startDate, $endDate]))->lazyById(200),
            ['total' => 'contract_value'], 'effective_date', auth()->user()->preference('currency', 'NOK'),
            ['type' => fn ($row) => $row->contract_type ?: 'Other']);

        $statusBreakdown = Contract::where('user_id', $userId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => [
                'status' => $item->status ?: 'Unknown',
                'count' => $item->count,
            ]);

        $typeDistribution = collect($summary['groups']['type'])->map(fn ($item, $type) => [
            'type' => $type, 'count' => $item['count'], 'value' => $item['total'],
        ])->sortByDesc('count')->values();

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
                'value' => $item->contract_value, 'currency' => $item->currency,
                'days_until_expiry' => (int) now()->diffInDays($item->expiry_date),
            ]);

        return [
            'stats' => [
                'total' => $total,
                'active' => $active,
                'expired' => $expired,
                'total_value' => $summary['amounts']['total'],
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
            'month' => Carbon::now()->subMonthNoOverflow(),
            'quarter' => Carbon::now()->subMonthsNoOverflow(3),
            'year' => Carbon::now()->subYearNoOverflow(),
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
}
