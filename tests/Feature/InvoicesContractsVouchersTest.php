<?php

use App\Models\Contract;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders invoices index with structured data', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);

    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'payment_terms' => 'Net 30',
    ]);

    InvoiceLineItem::factory()->count(2)->create([
        'invoice_id' => $invoice->id,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices', 1)
            ->where('invoices.0.id', $invoice->id)
            ->where('invoices.0.line_items_count', 2)
            ->where('invoices.0.payment_terms', 'Net 30')
        );
});

it('renders contracts index with structured data', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'contract',
        'processing_type' => 'contract',
    ]);

    $contract = Contract::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'summary' => 'Service agreement for quarterly maintenance.',
        'parties' => [
            ['name' => 'Acme Corp', 'role' => 'provider'],
            ['name' => 'Jamie Doe', 'role' => 'client'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('contracts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Contracts/Index')
            ->has('contracts', 1)
            ->where('contracts.0.id', $contract->id)
            ->where('contracts.0.summary', $contract->summary)
            ->where('contracts.0.parties.0.name', 'Acme Corp')
        );
});

it('renders vouchers index with structured data', function () {
    $user = User::factory()->create();
    $merchant = Merchant::create([
        'name' => 'PaperPulse Shop',
    ]);
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'voucher',
        'processing_type' => 'voucher',
    ]);

    $voucher = Voucher::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'merchant_id' => $merchant->id,
        'voucher_type' => 'gift_card',
        'terms_and_conditions' => 'Valid for one year.',
    ]);

    $this->actingAs($user)
        ->get(route('vouchers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Vouchers/Index')
            ->has('vouchers', 1)
            ->where('vouchers.0.id', $voucher->id)
            ->where('vouchers.0.merchant.name', 'PaperPulse Shop')
            ->where('vouchers.0.terms_and_conditions', 'Valid for one year.')
        );
});

it('renders invoice show with file data', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);

    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    InvoiceLineItem::factory()->count(2)->create([
        'invoice_id' => $invoice->id,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->where('invoice.id', $invoice->id)
            ->where('invoice.file.id', $file->id)
            ->has('invoice.line_items', 2)
        );
});

it('renders contract show with file data', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'contract',
        'processing_type' => 'contract',
    ]);

    $contract = Contract::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->get(route('contracts.show', $contract))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Contracts/Show')
            ->where('contract.id', $contract->id)
            ->where('contract.file.id', $file->id)
        );
});

it('renders voucher show with merchant data', function () {
    $user = User::factory()->create();
    $merchant = Merchant::create([
        'name' => 'PaperPulse Store',
    ]);
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'voucher',
        'processing_type' => 'voucher',
    ]);

    $voucher = Voucher::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'merchant_id' => $merchant->id,
        'voucher_type' => 'gift_card',
    ]);

    $this->actingAs($user)
        ->get(route('vouchers.show', $voucher))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Vouchers/Show')
            ->where('voucher.id', $voucher->id)
            ->where('voucher.merchant.name', 'PaperPulse Store')
            ->where('voucher.file.id', $file->id)
        );
});
