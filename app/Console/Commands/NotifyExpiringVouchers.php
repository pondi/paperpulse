<?php

namespace App\Console\Commands;

use App\Models\NotificationHistory;
use App\Models\Voucher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class NotifyExpiringVouchers extends Command
{
    protected $signature = 'notify:expiring-vouchers {--days=30 : Number of days before expiry to notify}';

    protected $description = 'Send notifications for vouchers expiring soon';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('Days must be a positive number.');

            return Command::FAILURE;
        }

        $today = Carbon::today('UTC');
        $firstDate = $today->copy()->subDay()->toDateString();
        $lastDate = $today->copy()->addDays($days + 1)->toDateString();

        $notified = 0;
        $skipped = 0;

        Voucher::with(['user.preferences', 'merchant'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $firstDate)
            ->whereDate('expiry_date', '<=', $lastDate)
            ->where('is_redeemed', false)
            ->chunkById(100, function ($vouchers) use ($days, &$notified, &$skipped) {
                /** @var Voucher $voucher */
                foreach ($vouchers as $voucher) {
                    $user = $voucher->user;
                    if (! $user) {
                        $skipped++;

                        continue;
                    }

                    $baseDate = $user->currentDate();
                    $expiryDate = $voucher->expiry_date;
                    $expiryDateString = $expiryDate->toDateString();
                    if ($expiryDateString < $baseDate->toDateString()
                        || $expiryDateString > $baseDate->copy()->addDays($days)->toDateString()) {
                        $skipped++;

                        continue;
                    }

                    $notifyInApp = $user->preference('notify_voucher_expiring', false);
                    $notifyEmail = $user->preference('email_notify_voucher_expiring', false);

                    if (! $notifyInApp && ! $notifyEmail) {
                        $skipped++;

                        continue;
                    }

                    $daysRemaining = (int) $baseDate->diffInDays(Carbon::parse($expiryDateString, $baseDate->getTimezone()));
                    if (NotificationHistory::queueReminder($user, 'voucher', $voucher->id, $expiryDateString, $days, $daysRemaining)) {
                        $notified++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("Queued {$notified} voucher expiring notifications.");

        if ($skipped > 0) {
            $this->info("Skipped {$skipped} vouchers (preferences, missing user, or already notified).");
        }

        return Command::SUCCESS;
    }
}
