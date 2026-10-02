<?php

namespace App\Jobs\Notifications;

use App\Jobs\BaseJob;
use App\Models\User;
use App\Models\WeeklySummaryDelivery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SendWeeklySummary extends BaseJob
{
    public function __construct()
    {
        parent::__construct(Str::uuid());
        $this->jobName = 'Send Weekly Summary';
        $this->onConnection('database');
    }

    protected function handleJob(): void
    {
        User::query()->with('preferences')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $today = Carbon::now($user->formattingPreferences()['timezone']);
                if (strtolower($today->englishDayOfWeek) !== $user->preference('weekly_summary_day', 'monday') || $today->hour < 9
                    || (! $user->preference('notify_weekly_summary_ready', true) && ! $user->preference('email_notify_weekly_summary', false))) {
                    continue;
                }
                $user->getConnection()->transaction(function () use ($user, $today): void {
                    User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $delivery = WeeklySummaryDelivery::query()->firstOrCreate([
                        'user_id' => $user->id, 'period_start' => $today->copy()->subWeek()->toDateString(), 'period_end' => $today->toDateString(),
                    ]);
                    if ($delivery->wasRecentlyCreated) {
                        SendUserWeeklySummary::dispatch($delivery->id)->beforeCommit();
                    }
                });
            }
        });
    }
}
