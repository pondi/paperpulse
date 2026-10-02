<?php

namespace App\Jobs\Notifications;

use App\Models\NotificationHistory;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Notifications\VoucherExpiringNotification;
use App\Notifications\WarrantyEndingNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Throwable;

class DeliverExpiryReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $historyId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('expiry-reminder:'.$this->historyId))->releaseAfter(30)->expireAfter(120)];
    }

    public function failed(?Throwable $exception): void
    {
        NotificationHistory::query()->whereKey($this->historyId)->where('status', 'queued')
            ->update(['status' => 'failed', 'error' => $exception ? $exception::class : null]);
    }

    public function handle(): void
    {
        $history = NotificationHistory::query()->with('user.preferences')->findOrFail($this->historyId);
        if (in_array($history->status, ['sent', 'obsolete'], true)) {
            return;
        }
        $entity = match ($history->entity_type) {
            'voucher' => Voucher::withoutGlobalScope('user')->where('user_id', $history->user_id)->find($history->entity_id),
            'warranty' => Warranty::withoutGlobalScope('user')->where('user_id', $history->user_id)->find($history->entity_id),
        };
        $expiry = $history->entity_type === 'voucher' ? $entity?->expiry_date : $entity?->warranty_end_date;
        $today = $history->user->currentDate();
        if (! $entity || $expiry?->toDateString() !== $history->meta['expiry_date']
            || $expiry->toDateString() < $today->toDateString()
            || ($entity instanceof Voucher && $entity->is_redeemed)) {
            $history->update(['status' => 'obsolete']);

            return;
        }
        $daysRemaining = (int) $today->diffInDays(Carbon::parse($expiry->toDateString(), $today->getTimezone()));
        $notification = $entity instanceof Voucher
            ? new VoucherExpiringNotification($entity, $daysRemaining)
            : new WarrantyEndingNotification($entity, $daysRemaining);
        $delivered = $history->delivered_channels ?? [];
        try {
            foreach (array_diff($notification->via($history->user), $delivered) as $channel) {
                $history->user->notifyNow($notification, [$channel]);
                $delivered[] = $channel;
                $history->update(['delivered_channels' => $delivered]);
            }
            $history->update(['status' => 'sent', 'notified_at' => now(), 'error' => null]);
        } catch (Throwable $exception) {
            $history->update(['status' => 'failed', 'error' => $exception::class]);
            throw $exception;
        }
    }
}
