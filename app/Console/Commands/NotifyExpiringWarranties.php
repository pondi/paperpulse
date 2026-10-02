<?php

namespace App\Console\Commands;

use App\Models\NotificationHistory;
use App\Models\Warranty;
use App\Notifications\WarrantyEndingNotification;
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

                    $notifyInApp = $user->preference('notify_warranty_expiring', true);
                    $notifyEmail = $user->preference('email_notify_warranty_expiring', false);

                    if (! $notifyInApp && ! $notifyEmail) {
                        $skipped++;

                        continue;
                    }

                    $alreadyNotified = NotificationHistory::where('user_id', $user->id)
                        ->where('notification_type', 'warranty_ending')
                        ->where('entity_type', 'warranty')
                        ->where('entity_id', $warranty->id)
                        ->exists();

                    if ($alreadyNotified) {
                        $skipped++;

                        continue;
                    }

                    $daysRemaining = (int) $baseDate->diffInDays(Carbon::parse($expiryDateString, $baseDate->getTimezone()));

                    $user->notify(new WarrantyEndingNotification($warranty, $daysRemaining));

                    NotificationHistory::create([
                        'user_id' => $user->id,
                        'notification_type' => 'warranty_ending',
                        'entity_type' => 'warranty',
                        'entity_id' => $warranty->id,
                        'notified_at' => now(),
                        'meta' => [
                            'warranty_end_date' => $endDateValue?->toDateString(),
                            'days_remaining' => $daysRemaining,
                        ],
                    ]);

                    $notified++;
                }
            });

        $this->info("Sent {$notified} warranty ending notifications.");

        if ($skipped > 0) {
            $this->info("Skipped {$skipped} warranties (preferences, missing user, or already notified).");
        }

        return Command::SUCCESS;
    }
}
