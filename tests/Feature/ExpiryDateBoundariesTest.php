<?php

use App\Http\Resources\Inertia\VoucherInertiaResource;
use App\Models\ReturnPolicy;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Notifications\VoucherExpiringNotification;
use App\Notifications\WarrantyEndingNotification;
use App\Services\Search\SearchResultFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Process\Process;

it('uses inclusive local calendar dates for model UI search and reminders', function (int $offset, bool $expired, bool $expiring) {
    $this->travelTo(Carbon::parse('2026-10-02 22:30:00', 'UTC'));
    $user = User::factory()->create();
    $user->preferences()->create(['timezone' => 'Europe/Oslo']);
    $date = $user->currentDate()->addDays($offset)->toDateString();
    $voucher = Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => $date, 'is_redeemed' => false]);
    $warranty = Warranty::factory()->create(['user_id' => $user->id, 'warranty_end_date' => $date]);
    $policy = ReturnPolicy::factory()->create(['user_id' => $user->id, 'return_deadline' => $date, 'exchange_deadline' => $date]);
    $voucher->setRelation('user', $user);

    expect($voucher->isExpired())->toBe($expired)->and($voucher->isExpiringSoon())->toBe($expiring)
        ->and($voucher->toArray()['expiry_date'])->toBe($date)
        ->and($warranty->toArray()['warranty_end_date'])->toBe($date)
        ->and($policy->toArray()['return_deadline'])->toBe($date)
        ->and($policy->toArray()['exchange_deadline'])->toBe($date)
        ->and(VoucherInertiaResource::forIndex($voucher)->toArray(request())['expiry_date'])->toBe($date)
        ->and($voucher->toSearchableArray())->not->toHaveKey('is_expired');
    $search = app(SearchResultFormatter::class)->formatVouchers(collect([$voucher]))->first();
    expect($search['is_expired'])->toBe($expired)->and($search['is_expiring_soon'])->toBe($expiring);

    Notification::fake();
    $this->artisan('notify:expiring-vouchers')->assertSuccessful();
    $this->artisan('notify:expiring-warranties')->assertSuccessful();
    if ($expiring) {
        Notification::assertSentTo($user, VoucherExpiringNotification::class,
            fn ($notification) => $notification->toArray($user)['days_remaining'] === $offset);
        Notification::assertSentTo($user, WarrantyEndingNotification::class,
            fn ($notification) => $notification->toArray($user)['days_remaining'] === $offset);
    } else {
        Notification::assertNothingSent();
    }
})->with([
    'past' => [-1, true, false], 'today' => [0, false, true], 'tomorrow' => [1, false, true],
    '30 days' => [30, false, true], '31 days' => [31, false, false],
]);

it('keeps an expiry date valid until midnight in the owner timezone', function () {
    $user = User::factory()->create();
    $user->preferences()->create(['timezone' => 'America/Los_Angeles']);
    $voucher = Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => '2026-10-02']);
    $this->travelTo(Carbon::parse('2026-10-03 06:59:59', 'UTC'));
    expect($voucher->isExpired())->toBeFalse()->and($voucher->isExpiringSoon())->toBeTrue();
    $this->travelTo(Carbon::parse('2026-10-03 07:00:00', 'UTC'));
    expect($voucher->isExpired())->toBeTrue()->and($voucher->isExpiringSoon())->toBeFalse();
});

it('does not treat redeemed or undated vouchers as expiring', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $voucher = Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => now()->toDateString(), 'is_redeemed' => true]);
    expect($voucher->isExpiringSoon())->toBeFalse();
    $voucher->expiry_date = null;
    expect($voucher->isExpired())->toBeFalse()->and($voucher->isExpiringSoon())->toBeFalse();
});

it('calculates browser expiry boundaries by calendar day across timezones and daylight saving', function () {
    $process = new Process(['node', '--input-type=module', '-e', <<<'JS'
import assert from 'node:assert/strict';
import { daysUntilCalendarDate } from './resources/js/utils/datetime.ts';
const instant = new Date('2026-10-02T22:30:00Z');
for (const [date, days] of [['2026-10-02', -1], ['2026-10-03', 0], ['2026-10-04', 1], ['2026-11-02', 30], ['2026-11-03', 31]]) {
    assert.equal(daysUntilCalendarDate(date, 'Europe/Oslo', instant), days);
}
assert.equal(daysUntilCalendarDate('2026-10-02', 'America/Los_Angeles', instant), 0);
assert.equal(daysUntilCalendarDate('2026-03-30', 'Europe/Oslo', new Date('2026-03-28T12:00:00Z')), 2);
assert.equal(daysUntilCalendarDate(null, 'UTC', instant), null);
JS
    ], base_path());
    $process->mustRun();
    expect($process->getExitCode())->toBe(0);
});

it('counts expiring documents using the same local calendar window', function () {
    $this->travelTo(Carbon::parse('2026-10-02 22:30:00', 'UTC'));
    $user = User::factory()->create();
    $user->preferences()->create(['timezone' => 'Europe/Oslo']);
    foreach ([-1, 0, 1, 30, 31] as $offset) {
        $date = $user->currentDate()->addDays($offset)->toDateString();
        Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => $date, 'is_redeemed' => false]);
        Warranty::factory()->create(['user_id' => $user->id, 'warranty_end_date' => $date]);
    }

    $this->actingAs($user)->get(route('analytics.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tab_data.expiring_soon.vouchers', 3)
        ->where('tab_data.expiring_soon.warranties', 3));
});
