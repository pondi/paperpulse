<?php

namespace App\Console\Commands;

use App\Models\NotificationHistory;
use App\Models\Warranty;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class NotifyExpiringWarranties extends Command
{
    protected $signature = 'notify:expiring-warranties {--days=30 : Number of days before expiry to notify}';

    protected $description = 'Send notifications for warranties ending soon';

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

        Warranty::with('user.preferences')
            ->whereNotNull('warranty_end_date')
            ->whereDate('warranty_end_date', '>=', $firstDate)
            ->whereDate('warranty_end_date', '<=', $lastDate)
            ->chunkById(100, function ($warranties) use ($days, &$notified, &$skipped) {
                /** @var Warranty $warranty */
                foreach ($warranties as $warranty) {
                    $user = $warranty->user;
                    if (! $user) {
                        $skipped++;

                        continue;
                    }

                    $baseDate = $user->currentDate();
                    $endDateValue = $warranty->warranty_end_date;
                    $expiryDateString = $endDateValue->toDateString();
                    if ($expiryDateString < $baseDate->toDateString()
                        || $expiryDateString > $baseDate->copy()->addDays($days)->toDateString()) {
                        $skipped++;

                        continue;
                    }

                    $notifyInApp = $user->preference('notify_warranty_expiring', false);
                    $notifyEmail = $user->preference('email_notify_warranty_expiring', false);

                    if (! $notifyInApp && ! $notifyEmail) {
                        $skipped++;

                        continue;
                    }

                    $daysRemaining = (int) $baseDate->diffInDays(Carbon::parse($expiryDateString, $baseDate->getTimezone()));
                    if (NotificationHistory::queueReminder($user, 'warranty', $warranty->id, $expiryDateString, $days, $daysRemaining)) {
                        $notified++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("Queued {$notified} warranty ending notifications.");

        if ($skipped > 0) {
            $this->info("Skipped {$skipped} warranties (preferences, missing user, or already notified).");
        }

        return Command::SUCCESS;
    }
}
