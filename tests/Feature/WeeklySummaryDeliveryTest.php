<?php

use App\Jobs\Notifications\SendUserWeeklySummary;
use App\Jobs\Notifications\SendWeeklySummary;
use App\Models\Receipt;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WeeklySummaryDelivery;
use App\Notifications\WeeklySummary;
use App\Services\MonetarySummaryService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->realBus = app(Dispatcher::class);
    $this->realNotifications = app(ChannelManager::class);
    Bus::fake();
    Notification::fake();
});

it('claims the local reporting period once for in-app-only users', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 4)->setTime(21, 0));
    $user = User::factory()->create();
    UserPreference::create(['user_id' => $user->id, 'timezone' => 'Pacific/Auckland', 'weekly_summary_day' => 'monday', 'notify_weekly_summary_ready' => true, 'email_notify_weekly_summary' => false]);
    $early = User::factory()->create();
    UserPreference::create(['user_id' => $early->id, 'timezone' => 'Pacific/Honolulu', 'weekly_summary_day' => 'monday', 'notify_weekly_summary_ready' => true]);
    (new SendWeeklySummary)->handle();
    (new SendWeeklySummary)->handle();
    Bus::assertDispatchedTimes(SendUserWeeklySummary::class, 1);
    $this->assertDatabaseHas('weekly_summary_deliveries', ['user_id' => $user->id, 'period_start' => '2026-09-28', 'period_end' => '2026-10-05']);
    $this->assertDatabaseCount('weekly_summary_deliveries', 1);
});

it('delivers a seven-day calendar window once and leaves the next week disjoint', function (): void {
    $user = User::factory()->create();
    UserPreference::create(['user_id' => $user->id, 'notify_weekly_summary_ready' => true, 'email_notify_weekly_summary' => false]);
    foreach (['2026-09-27', '2026-09-28', '2026-10-04', '2026-10-05'] as $date) {
        Receipt::factory()->create(['user_id' => $user->id, 'receipt_date' => $date, 'total_amount' => 10, 'currency' => 'NOK']);
    }
    $delivery = WeeklySummaryDelivery::create(['user_id' => $user->id, 'period_start' => '2026-09-28', 'period_end' => '2026-10-05']);
    $job = new SendUserWeeklySummary($delivery->id);
    $job->handle(app(MonetarySummaryService::class));
    $job->handle(app(MonetarySummaryService::class));
    Notification::assertSentToTimes($user, WeeklySummary::class, 1);
    Notification::assertSentTo($user, WeeklySummary::class, function (WeeklySummary $notification, array $channels) use ($user): bool {
        $data = $notification->toArray($user);

        return $channels === ['database', 'broadcast'] && $data['total_receipts'] === 2 && $data['total_amount'] === 20.0
            && $data['week_start'] === '2026-09-28' && $data['week_end'] === '2026-10-04';
    });
    $next = WeeklySummaryDelivery::create(['user_id' => $user->id, 'period_start' => '2026-10-05', 'period_end' => '2026-10-12']);
    (new SendUserWeeklySummary($next->id))->handle(app(MonetarySummaryService::class));
    Notification::assertSentTo($user, WeeklySummary::class, fn ($notification): bool => $notification->toArray($user)['week_start'] === '2026-10-05' && $notification->toArray($user)['total_receipts'] === 1);
});

it('respects email-only and disabled summary channel preferences', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(12, 0));
    foreach ([false, true] as $email) {
        $user = User::factory()->create();
        UserPreference::create(['user_id' => $user->id, 'weekly_summary_day' => 'monday', 'timezone' => 'UTC', 'notify_weekly_summary_ready' => false, 'email_notify_weekly_summary' => $email]);
    }
    (new SendWeeklySummary)->handle();
    Bus::assertDispatchedTimes(SendUserWeeklySummary::class, 1);
    $delivery = WeeklySummaryDelivery::first();
    (new SendUserWeeklySummary($delivery->id))->handle(app(MonetarySummaryService::class));
    Notification::assertSentTo($delivery->user, WeeklySummary::class, fn ($notification, $channels): bool => $channels === ['mail']);
});

it('rolls back a failed notification handoff so the same period can retry', function (): void {
    $user = User::factory()->create();
    $delivery = WeeklySummaryDelivery::create(['user_id' => $user->id, 'period_start' => '2026-09-28', 'period_end' => '2026-10-05']);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Queue offline'));
    $job = new SendUserWeeklySummary($delivery->id);
    expect(fn () => $job->handle(app(MonetarySummaryService::class)))->toThrow(RuntimeException::class, 'Queue offline');
    expect($delivery->fresh()->status)->toBe('queued');
    Notification::fake();
    $job->handle(app(MonetarySummaryService::class));
    Notification::assertSentToTimes($user, WeeklySummary::class, 1);
    expect($delivery->fresh()->status)->toBe('completed');
});

it('persists channel jobs on the database queue with the completed period claim', function (): void {
    Notification::swap($this->realNotifications);
    Bus::swap($this->realBus);
    $user = User::factory()->create();
    UserPreference::create(['user_id' => $user->id, 'notify_weekly_summary_ready' => true, 'email_notify_weekly_summary' => false]);
    $delivery = WeeklySummaryDelivery::create(['user_id' => $user->id, 'period_start' => '2026-09-28', 'period_end' => '2026-10-05']);
    (new SendUserWeeklySummary($delivery->id))->handle(app(MonetarySummaryService::class));
    $this->assertDatabaseCount('jobs', 2);
    expect($delivery->fresh()->status)->toBe('completed');
});
