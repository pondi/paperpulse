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
        'user_id' => $user->id,
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
        'user_id' => $user->id,
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

// ==========================================
// Invoice CRUD Operations
// ==========================================

it('can update an invoice', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'invoice',
    ]);
    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'invoice_number' => 'INV-001',
    ]);

    $this->actingAs($user)
        ->patch(route('invoices.update', $invoice), [
            'invoice_number' => 'INV-002',
            'payment_status' => 'paid',
        ])
        ->assertRedirect();

    expect($invoice->fresh())
        ->invoice_number->toBe('INV-002')
        ->payment_status->toBe('paid');
});

it('cannot update another users invoice', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $otherUser->id,
        'file_type' => 'invoice',
    ]);
    $invoice = Invoice::factory()->create([
        'user_id' => $otherUser->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->patch(route('invoices.update', $invoice), [
            'invoice_number' => 'HACKED',
        ])
        ->assertNotFound();
});

it('can delete an invoice', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'invoice',
        'guid' => 'test-guid-invoice',
    ]);
    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    \App\Models\ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'entity_type' => 'invoice',
        'entity_id' => $invoice->id,
        'is_primary' => true,
        'extraction_provider' => 'test',
        'extracted_at' => now(),
    ]);

    $storageService = $this->mock(\App\Services\StorageService::class);
    $storageService->shouldReceive('deleteFile')->once()->andReturn(true);

    $this->actingAs($user)
        ->delete(route('invoices.destroy', $invoice))
        ->assertRedirect(route('invoices.index'));

    expect(Invoice::withTrashed()->find($invoice->id)->trashed())->toBeTrue();
});

it('can download an invoice file', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'invoice',
        'guid' => 'test-guid-download',
        'fileExtension' => 'pdf',
    ]);
    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $storageService = $this->mock(\App\Services\StorageService::class);
    $storageService->shouldReceive('getFileByUserAndGuid')
        ->once()
        ->andReturn('fake-pdf-content');

    $this->actingAs($user)
        ->get(route('invoices.download', $invoice))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('Content-Disposition');
});

it('can attach a tag to an invoice', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'invoice',
    ]);
    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->post(route('invoices.tags.store', $invoice), [
            'name' => 'important',
        ])
        ->assertRedirect();

    expect($file->fresh()->tags)->toHaveCount(1);
    expect($file->fresh()->tags->first()->name)->toBe('important');
});

it('can detach a tag from an invoice', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'invoice',
    ]);
    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $tag = \App\Models\Tag::create([
        'name' => 'removeme',
        'user_id' => $user->id,
        'slug' => 'removeme',
        'color' => '#ff0000',
    ]);
    $file->tags()->attach($tag);

    $this->actingAs($user)
        ->delete(route('invoices.tags.destroy', [$invoice, $tag]))
        ->assertRedirect();

    expect($file->fresh()->tags)->toHaveCount(0);
});

// ==========================================
// Contract CRUD Operations
// ==========================================

it('can update a contract', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'contract',
    ]);
    $contract = Contract::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'contract_title' => 'Old Title',
    ]);

    $this->actingAs($user)
        ->patch(route('contracts.update', $contract), [
            'contract_title' => 'New Title',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($contract->fresh())
        ->contract_title->toBe('New Title')
        ->status->toBe('active');
});

it('cannot update another users contract', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $otherUser->id,
        'file_type' => 'contract',
    ]);
    $contract = Contract::factory()->create([
        'user_id' => $otherUser->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->patch(route('contracts.update', $contract), [
            'contract_title' => 'HACKED',
        ])
        ->assertNotFound();
});

it('can delete a contract', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'contract',
        'guid' => 'test-guid-contract',
    ]);
    $contract = Contract::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    \App\Models\ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'entity_type' => 'contract',
        'entity_id' => $contract->id,
        'is_primary' => true,
        'extraction_provider' => 'test',
        'extracted_at' => now(),
    ]);

    $storageService = $this->mock(\App\Services\StorageService::class);
    $storageService->shouldReceive('deleteFile')->once()->andReturn(true);

    $this->actingAs($user)
        ->delete(route('contracts.destroy', $contract))
        ->assertRedirect(route('contracts.index'));

    expect(Contract::withTrashed()->find($contract->id)->trashed())->toBeTrue();
});

it('can download a contract file', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'contract',
        'guid' => 'test-guid-contract-dl',
        'fileExtension' => 'pdf',
    ]);
    $contract = Contract::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $storageService = $this->mock(\App\Services\StorageService::class);
    $storageService->shouldReceive('getFileByUserAndGuid')
        ->once()
        ->andReturn('fake-pdf-content');

    $this->actingAs($user)
        ->get(route('contracts.download', $contract))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('Content-Disposition');
});

it('can attach a tag to a contract', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'contract',
    ]);
    $contract = Contract::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $this->actingAs($user)
        ->post(route('contracts.tags.store', $contract), [
            'name' => 'legal',
        ])
        ->assertRedirect();

    expect($file->fresh()->tags)->toHaveCount(1);
    expect($file->fresh()->tags->first()->name)->toBe('legal');
});

it('can detach a tag from a contract', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'contract',
    ]);
    $contract = Contract::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
    ]);

    $tag = \App\Models\Tag::create([
        'name' => 'removeme',
        'user_id' => $user->id,
        'slug' => 'removeme-contract',
        'color' => '#ff0000',
    ]);
    $file->tags()->attach($tag);

    $this->actingAs($user)
        ->delete(route('contracts.tags.destroy', [$contract, $tag]))
        ->assertRedirect();

    expect($file->fresh()->tags)->toHaveCount(0);
});
