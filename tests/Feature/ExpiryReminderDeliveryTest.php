<?php

use App\Jobs\Notifications\DeliverExpiryReminder;
use App\Models\NotificationHistory;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Notifications\VoucherExpiringNotification;
use App\Notifications\WarrantyEndingNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

it('claims each expiry window once and permits a changed expiry', function (string $model, string $dateField, string $command, string $notification) {
    $user = User::factory()->create();
    $user->preferences()->create(['notify_voucher_expiring' => true, 'notify_warranty_expiring' => true]);
    $entity = $model::factory()->create(['user_id' => $user->id, $dateField => $user->currentDate()->addDays(5)->toDateString()]);
    Notification::fake();
    Bus::fake();

    $this->artisan($command)->assertSuccessful();
    $this->artisan($command)->assertSuccessful();
    Bus::assertDispatchedTimes(DeliverExpiryReminder::class, 1);
    $history = NotificationHistory::query()->sole();
    expect($history->status)->toBe('queued')->and($history->notified_at)->toBeNull();
    (new DeliverExpiryReminder($history->id))->handle();
    (new DeliverExpiryReminder($history->id))->handle();
    Notification::assertSentToTimes($user, $notification, 2);
    expect($history->fresh()->status)->toBe('sent');

    $entity->update([$dateField => $user->currentDate()->addDays(10)->toDateString()]);
    $this->artisan($command)->assertSuccessful();
    Bus::assertDispatchedTimes(DeliverExpiryReminder::class, 2);
    expect($history->fresh()->status)->toBe('obsolete');
    $new = NotificationHistory::query()->where('status', 'queued')->sole();
    (new DeliverExpiryReminder($new->id))->handle();
    expect($new->fresh()->status)->toBe('sent');
})->with([
    [Voucher::class, 'expiry_date', 'notify:expiring-vouchers', VoucherExpiringNotification::class],
    [Warranty::class, 'warranty_end_date', 'notify:expiring-warranties', WarrantyEndingNotification::class],
]);

it('retries a failed channel without repeating successful channels', function () {
    $user = User::factory()->create();
    $user->preferences()->create(['notify_voucher_expiring' => true, 'email_notify_voucher_expiring' => true]);
    $voucher = Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => $user->currentDate()->addDays(5)->toDateString()]);
    Bus::fake();
    $this->artisan('notify:expiring-vouchers')->assertSuccessful();
    $history = NotificationHistory::query()->sole();
    $calls = [];
    Notification::shouldReceive('sendNow')->andReturnUsing(function ($user, $notification, array $channels) use (&$calls): void {
        $calls[] = $channels[0];
        if ($channels === ['mail']) {
            throw new RuntimeException('Mail unavailable');
        }
    });
    expect(fn () => (new DeliverExpiryReminder($history->id))->handle())->toThrow(RuntimeException::class);
    expect($history->fresh()->status)->toBe('failed')
        ->and($history->fresh()->delivered_channels)->toBe(['database', 'broadcast']);

    $this->artisan('notify:expiring-vouchers')->assertSuccessful();
    $this->artisan('notify:expiring-vouchers')->assertSuccessful();
    Bus::assertDispatchedTimes(DeliverExpiryReminder::class, 2);
    Notification::fake();
    (new DeliverExpiryReminder($history->id))->handle();
    Notification::assertSentTo($user, VoucherExpiringNotification::class, fn ($notification, $channels) => $channels === ['mail']);
    expect($history->fresh()->status)->toBe('sent')->and($history->fresh()->error)->toBeNull()
        ->and($calls)->toBe(['database', 'broadcast', 'mail']);
});

it('skips obsolete queued expiry versions and serializes delivery with database locks', function () {
    $user = User::factory()->create();
    $user->preferences()->create(['notify_voucher_expiring' => true]);
    $voucher = Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => $user->currentDate()->addDays(5)->toDateString()]);
    Bus::fake();
    Notification::fake();
    $this->artisan('notify:expiring-vouchers')->assertSuccessful();
    $history = NotificationHistory::query()->sole();
    $job = new DeliverExpiryReminder($history->id);
    $middleware = $job->middleware()[0];
    $lock = Cache::store('database')->lock($middleware->getLockKey($job), 120);
    expect($lock->get())->toBeTrue()->and(Cache::store('database')->lock($middleware->getLockKey($job), 120)->get())->toBeFalse();
    $lock->release();
    expect(Cache::store('database')->lock($middleware->getLockKey($job), 120)->get())->toBeTrue();

    $voucher->update(['expiry_date' => $user->currentDate()->addDays(10)->toDateString()]);
    $job->handle();
    expect($history->fresh()->status)->toBe('obsolete');
    Notification::assertNothingSent();
});
