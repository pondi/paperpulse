<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\User;
use App\Support\SpreadsheetSafeText;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReceiptExportService
{
    /** @return Builder<Receipt> */
    public function query(int $userId, array $filters): Builder
    {
        return Receipt::withoutGlobalScope('user')->where('user_id', $userId)->with(['merchant', 'lineItems', 'file'])
            ->when($filters['from_date'] ?? null, fn ($query, $date) => $query->whereDate('receipt_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($query, $date) => $query->whereDate('receipt_date', '<=', $date))
            ->when($filters['merchant_id'] ?? null, fn ($query, $id) => $query->where('merchant_id', $id))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('receipt_category', $category))
            ->when($filters['receipt_ids'] ?? null, fn ($query, $ids) => $query->whereIn('id', $ids))
            ->orderBy($filters['sort'] ?? 'receipt_date', $filters['sort_direction'] ?? 'desc')->orderBy('id');
    }

    /**
     * @param  Builder<Receipt>  $query
     * @param  resource  $stream
     */
    public function writeCsv(Builder $query, mixed $stream, bool $includeNote = true): void
    {
        $header = ['Receipt Date', 'Merchant', 'Category', 'Description'];
        if ($includeNote) {
            $header[] = 'Note';
        }
        fputcsv($stream, [...$header, 'Total Amount', 'Tax Amount', 'Currency', 'Items Count', 'Line Items'], ',', '"', '');
        foreach ($query->lazy(200) as $receipt) {
            $row = [$receipt->receipt_date?->format('Y-m-d') ?? '', SpreadsheetSafeText::format($receipt->merchant?->name ?? 'Unknown'),
                SpreadsheetSafeText::format($receipt->receipt_category ?? ''), SpreadsheetSafeText::format($receipt->receipt_description ?? '')];
            if ($includeNote) {
                $row[] = SpreadsheetSafeText::format($receipt->note ?? '');
            }
            $items = $receipt->lineItems->map(fn ($item) => $item->text.' (Qty: '.$item->qty.', Price: '.$item->price.')')->implode('; ');
            fputcsv($stream, [...$row, $receipt->total_amount ?? 0, $receipt->tax_amount ?? 0,
                SpreadsheetSafeText::format($receipt->currency ?? ''), $receipt->lineItems->count(), SpreadsheetSafeText::format($items)], ',', '"', '');
        }
    }

    /** @param Collection<int, Receipt> $receipts */
    public function pdf(Collection $receipts, User $user, array $filters, ?array $summary = null): \Barryvdh\DomPDF\PDF
    {
        $currency = $user->preference('currency', 'NOK');
        $summary ??= app(MonetarySummaryService::class)->aggregate($receipts, ['total' => 'total_amount'], 'receipt_date', $currency);

        return Pdf::loadView('exports.receipts-pdf', [
            'receipts' => $receipts, 'from_date' => $filters['from_date'] ?? null, 'to_date' => $filters['to_date'] ?? null,
            'generated_at' => now(), 'total_amount' => $summary['amounts']['total'], 'total_count' => $summary['count'], 'currency' => $currency,
        ]);
    }
}
