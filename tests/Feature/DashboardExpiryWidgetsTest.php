<?php

use App\Models\User;
use App\Models\UserPreference;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    Bus::fake();
    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(23, 30));
});

it('shows bounded owner expiry widgets using the local calendar date', function (): void {
    $user = User::factory()->create();
    UserPreference::create(['user_id' => $user->id, 'timezone' => 'Europe/Oslo']);
    Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => '2026-10-02', 'is_redeemed' => false, 'current_value' => 0, 'currency' => 'EUR']);
    Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => '2026-10-01', 'is_redeemed' => false]);
    Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => '2026-10-02', 'is_redeemed' => true]);
    Voucher::factory()->create(['user_id' => User::factory()->create()->id, 'expiry_date' => '2026-10-02', 'is_redeemed' => false]);
    Warranty::factory()->count(6)->create(['user_id' => $user->id, 'warranty_end_date' => '2026-11-01']);
    Warranty::factory()->create(['user_id' => $user->id, 'warranty_end_date' => '2026-10-02'])->delete();
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('expiringVouchers.items', 1)->where('expiringVouchers.total', 1)
        ->where('expiringVouchers.items.0.days_remaining', 0)->where('expiringVouchers.items.0.currency', 'EUR')
        ->has('endingWarranties.items', 5)->where('endingWarranties.total', 6)->where('endingWarranties.items.0.days_remaining', 30));
});

it('shows empty expiry widget states for new users', function (): void {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('expiringVouchers.items', 0)->where('expiringVouchers.total', 0)
        ->has('endingWarranties.items', 0)->where('endingWarranties.total', 0));
});
