<?php

namespace App\Jobs\Notifications;

use App\Models\Receipt;
use App\Models\WeeklySummaryDelivery;
use App\Notifications\WeeklySummary;
use App\Services\MonetarySummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class SendUserWeeklySummary implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId)
    {
        $this->onConnection('database');
    }

    public function handle(MonetarySummaryService $summaries): void
    {
        $delivery = WeeklySummaryDelivery::query()->findOrFail($this->deliveryId);
        if ($delivery->status === 'completed') {
            return;
        }
        $user = $delivery->user()->with('preferences')->firstOrFail();
        $currency = $user->preference('currency', 'NOK');
        $summary = $summaries->aggregate(Receipt::withoutGlobalScope('user')->where('user_id', $user->id)
            ->whereDate('receipt_date', '>=', $delivery->period_start)->whereDate('receipt_date', '<', $delivery->period_end)
            ->with(['merchant', 'category'])->lazyById(200), ['total' => 'total_amount'], 'receipt_date', $currency,
            ['category' => fn ($row) => $row->category?->name ?? 'Uncategorized', 'merchant' => fn ($row) => $row->merchant?->name ?? 'Unknown']);
        $data = [
            'week_start' => Carbon::parse($delivery->period_start), 'week_end' => Carbon::parse($delivery->period_end)->subDay(),
            'total_receipts' => $summary['count'], 'total_amount' => $summary['amounts']['total'], 'currency' => $currency,
            'categories' => collect($summary['groups']['category'])->map(fn ($group) => $group['count']),
            'merchants' => collect($summary['groups']['merchant'])->map(fn ($group) => $group['count']),
            'average_amount' => $summary['amounts']['total'] === null ? null : ($summary['count'] > 0 ? $summary['amounts']['total'] / $summary['count'] : 0),
        ];
        $delivery->getConnection()->transaction(function () use ($data, $user): void {
            $delivery = WeeklySummaryDelivery::query()->lockForUpdate()->findOrFail($this->deliveryId);
            if ($delivery->status === 'completed') {
                return;
            }
            $user->notify((new WeeklySummary($data))->onConnection('database')->beforeCommit());
            $delivery->update(['status' => 'completed']);
        });
    }
}
