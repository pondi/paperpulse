<?php

use App\Models\User;
use App\Models\UserPreference;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Notifications\VoucherExpiringNotification;
use App\Notifications\WarrantyEndingNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-02 22:30:00', 'UTC'));
});

it('persists reminder channel choices and exposes them after reload', function () {
    $user = User::factory()->create();
    $payload = array_replace(UserPreference::defaultPreferences(), [
        'notify_voucher_expiring' => true, 'notify_warranty_expiring' => false,
        'email_notify_voucher_expiring' => false, 'email_notify_warranty_expiring' => true,
        'timezone' => 'Europe/Oslo',
    ]);

    $this->actingAs($user)->patch(route('preferences.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $user->refresh();
    $this->get(route('preferences.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('preferences.notify_voucher_expiring', true)
        ->where('preferences.notify_warranty_expiring', false)
        ->where('preferences.email_notify_voucher_expiring', false)
        ->where('preferences.email_notify_warranty_expiring', true));
    $this->patch(route('preferences.update'), UserPreference::defaultPreferences())->assertSessionHasNoErrors();
    expect($user->preferences()->first()->notify_voucher_expiring)->toBeFalse()
        ->and($user->preferences()->first()->email_notify_warranty_expiring)->toBeFalse();
});

it('rejects invalid reminder switches', function (string $field) {
    $user = User::factory()->create();
    $payload = array_replace(UserPreference::defaultPreferences(), [$field => 'invalid']);
    $this->actingAs($user)->patch(route('preferences.update'), $payload)->assertSessionHasErrors($field);
    expect($user->preferences()->exists())->toBeFalse();
})->with(['notify_voucher_expiring', 'notify_warranty_expiring', 'email_notify_voucher_expiring', 'email_notify_warranty_expiring']);

it('sends no reminders unless a channel has been enabled', function (bool $hasPreferences) {
    $user = User::factory()->create();
    if ($hasPreferences) {
        $user->preferences()->create(['timezone' => 'Europe/Oslo']);
    }
    $voucher = Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => '2026-10-03']);
    $warranty = Warranty::factory()->create(['user_id' => $user->id, 'warranty_end_date' => '2026-10-03']);
    Notification::fake();

    $this->artisan('notify:expiring-vouchers')->assertSuccessful();
    $this->artisan('notify:expiring-warranties')->assertSuccessful();
    Notification::assertNothingSent();
    expect((new VoucherExpiringNotification($voucher, 0))->via($user))->toBe([])
        ->and((new WarrantyEndingNotification($warranty, 0))->via($user))->toBe([]);
    $this->assertDatabaseCount('notification_history', 0);
})->with([false, true]);

it('uses the selected channels and local expiry window for opted in users', function (bool $inApp, bool $email, array $channels) {
    $user = User::factory()->create();
    $user->preferences()->create([
        'timezone' => 'Europe/Oslo',
        'notify_voucher_expiring' => $inApp, 'email_notify_voucher_expiring' => $email,
        'notify_warranty_expiring' => $inApp, 'email_notify_warranty_expiring' => $email,
    ]);
    $voucher = Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => '2026-10-03', 'is_redeemed' => false]);
    $warranty = Warranty::factory()->create(['user_id' => $user->id, 'warranty_end_date' => '2026-10-03']);
    Notification::fake();

    $this->artisan('notify:expiring-vouchers')->assertSuccessful();
    $this->artisan('notify:expiring-warranties')->assertSuccessful();
    Notification::assertSentTo($user, VoucherExpiringNotification::class,
        fn ($notification, $actualChannels) => $actualChannels === $channels && $notification->toArray($user)['days_remaining'] === 0);
    Notification::assertSentTo($user, WarrantyEndingNotification::class,
        fn ($notification, $actualChannels) => $actualChannels === $channels && $notification->toArray($user)['days_remaining'] === 0);
    expect((new VoucherExpiringNotification($voucher, 0))->via($user))->toBe($channels)
        ->and((new WarrantyEndingNotification($warranty, 0))->via($user))->toBe($channels);
})->with([
    'in app' => [true, false, ['database', 'broadcast']],
    'email' => [false, true, ['mail']],
    'both' => [true, true, ['database', 'broadcast', 'mail']],
]);

it('schedules daily UTC reminders with database cache overlap protection', function () {
    config(['cache.default' => 'database']);
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => in_array($event->description, ['notify-expiring-vouchers', 'notify-expiring-warranties'], true));
    expect($events)->toHaveCount(2);
    foreach ($events as $event) {
        expect($event->expression)->toBe('0 8 * * *')->and($event->timezone)->toBe('UTC')
            ->and($event->command)->toContain('--days=30')->and($event->withoutOverlapping)->toBeTrue();
        expect($event->mutex->create($event))->toBeTrue()->and($event->mutex->create($event))->toBeFalse();
        $event->mutex->forget($event);
        expect($event->mutex->exists($event))->toBeFalse();
    }
});

it('delivers an opted in reminder through a database queue worker', function () {
    $user = User::factory()->create();
    $user->preferences()->create(['notify_voucher_expiring' => true]);
    Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => '2026-10-03', 'is_redeemed' => false]);

    config(['queue.default' => 'database', 'cache.default' => 'database']);
    $this->artisan('notify:expiring-vouchers')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 0);
    $this->assertDatabaseCount('jobs', 2);
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])->assertSuccessful();
    expect($user->notifications()->first()->data['type'])->toBe('voucher_expiring');
});

it('disables hidden legacy defaults without changing other preferences', function () {
    $user = User::factory()->create();
    $preferences = $user->preferences()->create([
        'timezone' => 'Europe/Oslo', 'notify_voucher_expiring' => true, 'notify_warranty_expiring' => true,
        'email_notify_voucher_expiring' => true,
    ]);
    $migration = require database_path('migrations/2026_10_02_065832_make_expiry_reminders_opt_in.php');
    $migration->up();
    $preferences->refresh();
    expect($preferences->notify_voucher_expiring)->toBeFalse()->and($preferences->notify_warranty_expiring)->toBeFalse()
        ->and($preferences->timezone)->toBe('Europe/Oslo')->and($preferences->email_notify_voucher_expiring)->toBeTrue();
});
