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

it('opens receipts in the file workspace with an accessible return destination', function () {
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
        ->assertRedirect(route('files.show', $file));

    $this->get(route('files.show', $file))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Files/Show')
            ->where('file.id', $file->id)
            ->where('file.back_url', route('library.index'))
            ->where('file.back_label', 'Back to Library')
            ->where('extractedEntities.0.entity_id', $receipt->id)
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

it('opens documents in the file workspace with an accessible return destination', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $document = Document::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->get(route('documents.show', $document))
        ->assertRedirect(route('files.show', $file));

    $this->get(route('files.show', $file))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Files/Show')
            ->where('file.id', $file->id)
            ->where('file.back_url', route('library.index'))
            ->where('file.back_label', 'Back to Library')
            ->where('extractedEntities.0.entity_id', $document->id)
        );
});
