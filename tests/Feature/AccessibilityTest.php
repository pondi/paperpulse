<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\Document;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders receipt show page with breadcrumbs prop', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $merchant = Merchant::create(['name' => 'Test Store', 'user_id' => $user->id]);
    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'merchant_id' => $merchant->id,
    ]);

    $this->actingAs($user)
        ->get(route('receipts.show', $receipt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Receipt/Show')
            ->has('breadcrumbs')
        );
});

it('renders invoice show page with breadcrumbs prop', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Invoices/Show')
            ->has('breadcrumbs')
        );
});

it('renders contract show page with breadcrumbs prop', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $contract = Contract::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->get(route('contracts.show', $contract))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Contracts/Show')
            ->has('breadcrumbs')
        );
});

it('renders voucher show page with breadcrumbs prop', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $voucher = Voucher::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->get(route('vouchers.show', $voucher))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Vouchers/Show')
            ->has('breadcrumbs')
        );
});

it('renders document show page with breadcrumbs prop', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $document = Document::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Documents/Show')
            ->has('breadcrumbs')
        );
});
