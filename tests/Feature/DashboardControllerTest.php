<?php

declare(strict_types=1);

use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutVite());

it('serializes multiple tagged receipts without lazy loading', function () {
    $user = User::factory()->create();
    $receipts = Receipt::factory()->count(3)->create([
        'user_id' => $user->id, 'currency' => 'NOK', 'total_amount' => 10,
    ]);
    $tag = Tag::factory()->create(['user_id' => $user->id]);
    foreach ($receipts as $receipt) {
        $receipt->file->tags()->attach($tag);
    }
    Model::preventLazyLoading();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('recentReceipts', 3)
            ->where('totalAmount', 30)
            ->where('recentReceipts.0.currency', 'NOK'));
});

it('requires authentication', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('shows dashboard with stats', function () {
    $user = User::factory()->create();
    $merchant = Merchant::create(['name' => 'Store A', 'user_id' => $user->id]);

    Receipt::factory()->count(3)->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'currency' => 'NOK', 'total_amount' => 100.00,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('receiptCount', 3)
            ->where('totalAmount', 300)
            ->where('merchantCount', 1)
            ->has('recentReceipts', 3)
        );
});

it('shows empty dashboard for new user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('receiptCount', 0)
            ->where('totalAmount', 0)
            ->where('merchantCount', 0)
            ->has('recentReceipts', 0)
        );
});

it('isolates dashboard data by user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Receipt::factory()->count(5)->create(['user_id' => $other->id, 'currency' => 'NOK', 'total_amount' => 500.00]);
    Receipt::factory()->count(2)->create(['user_id' => $user->id, 'currency' => 'NOK', 'total_amount' => 50.00]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('receiptCount', 2)
            ->where('totalAmount', 100)
        );
});
