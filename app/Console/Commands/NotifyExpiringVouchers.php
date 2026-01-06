<?php

namespace App\Console\Commands;

use App\Models\NotificationHistory;
use App\Models\Voucher;
use App\Notifications\VoucherExpiringNotification;
use Illuminate\Console\Command;

class NotifyExpiringVouchers extends Command
{
    protected $signature = 'notify:expiring-vouchers {--days=30 : Number of days before expiry to notify}';

    protected $description = 'Send notifications for vouchers expiring soon';

    public function handle()
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('Days must be a positive number.');

            return Command::FAILURE;
        }

        $now = now();
        $today = $now->toDateString();
        $endDate = $now->copy()->addDays($days)->toDateString();
        $baseDate = $now->copy()->startOfDay();

        $notified = 0;
        $skipped = 0;

        Voucher::with(['user', 'merchant'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $endDate)
            ->where('is_redeemed', false)
            ->orderBy('expiry_date')
            ->chunkById(100, function ($vouchers) use ($baseDate, &$notified, &$skipped) {
                /** @var Voucher $voucher */
                foreach ($vouchers as $voucher) {
                    $user = $voucher->user;
                    if (! $user) {
                        $skipped++;

                        continue;
                    }

                    $notifyInApp = $user->preference('notify_voucher_expiring', true);
                    $notifyEmail = $user->preference('email_notify_voucher_expiring', false);

                    if (! $notifyInApp && ! $notifyEmail) {
                        $skipped++;

                        continue;
                    }

                    $alreadyNotified = NotificationHistory::where('user_id', $user->id)
                        ->where('notification_type', 'voucher_expiring')
                        ->where('entity_type', 'voucher')
                        ->where('entity_id', $voucher->id)
                        ->exists();

                    if ($alreadyNotified) {
                        $skipped++;

                        continue;
                    }

                    $expiryDate = $voucher->expiry_date;
                    $daysRemaining = $expiryDate
                        ? $baseDate->diffInDays($expiryDate->copy()->startOfDay())
                        : 0;

                    $user->notify(new VoucherExpiringNotification($voucher, $daysRemaining));

                    NotificationHistory::create([
                        'user_id' => $user->id,
                        'notification_type' => 'voucher_expiring',
                        'entity_type' => 'voucher',
                        'entity_id' => $voucher->id,
                        'notified_at' => now(),
                        'meta' => [
                            'expiry_date' => $expiryDate?->toDateString(),
                            'days_remaining' => $daysRemaining,
                        ],
                    ]);

                    $notified++;
                }
            });

        $this->info("Sent {$notified} voucher expiring notifications.");

        if ($skipped > 0) {
            $this->info("Skipped {$skipped} vouchers (preferences, missing user, or already notified).");
        }

        return Command::SUCCESS;
    }
}
