<?php

declare(strict_types=1);

use App\Models\File;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

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

it('shows recent mixed archive uploads and outstanding work without exposing foreign files', function (): void {
    $owner = User::factory()->create();
    $files = collect(['receipt', 'invoice', 'document', 'contract', 'voucher', 'bank_statement', 'warranty'])->map(function (string $type, int $index) use ($owner): File {
        return File::factory()->create(['user_id' => $owner->id, 'file_type' => $type,
            'fileName' => $type.'.pdf', 'uploaded_at' => now()->subMinutes(7 - $index),
            'status' => ['completed', 'failed', 'pending', 'processing', 'needs_review', 'completed', 'completed'][$index]]);
    });
    File::factory()->create(['fileName' => 'Foreign private file', 'uploaded_at' => now(), 'status' => 'failed']);
    $files->last()->delete();
    $this->actingAs($owner)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('archiveStats', ['total' => 6, 'processing' => 2, 'failed' => 1, 'needs_review' => 1])
            ->has('recentUploads', 6)->where('recentUploads.0.name', 'bank_statement.pdf')
            ->where('recentUploads.5.name', 'receipt.pdf')->where('receiptCount', 0));
});

it('provides an empty archive overview for new accounts', function (): void {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('archiveStats', ['total' => 0, 'processing' => 0, 'failed' => 0, 'needs_review' => 0])
            ->has('recentUploads', 0));
});
