<?php

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\File;
use App\Models\Invoice;
use App\Models\ReturnPolicy;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->searchService = app(SearchService::class);
});

// ─── Type filtering ──────────────────────────────────────────────

it('returns empty results when query is empty and no filters', function () {
    $result = $this->searchService->search('');

    expect($result['results'])->toBeEmpty()
        ->and($result['facets']['total'])->toBe(0);
});

it('searches invoices and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);

    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'from_name' => 'Visma Solutions',
        'invoice_number' => 'INV-99999',
        'total_amount' => 1500.00,
        'currency' => 'NOK',
    ]);

    // Search by invoice_number which is in toSearchableArray
    $result = $this->searchService->search('INV-99999', ['type' => 'invoice']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('invoice')
        ->and($result['results'][0]['title'])->toBe('Visma Solutions');
});

it('searches contracts and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'contract',
        'processing_type' => 'contract',
    ]);

    Contract::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'contract_title' => 'Service Agreement Alpha',
        'contract_type' => 'service',
    ]);

    $result = $this->searchService->search('Alpha', ['type' => 'contract']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('contract')
        ->and($result['results'][0]['title'])->toBe('Service Agreement Alpha');
});

it('searches vouchers and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'voucher',
        'processing_type' => 'voucher',
    ]);

    Voucher::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'code' => 'SUMMER-DEAL-50',
        'current_value' => 500.00,
        'currency' => 'NOK',
    ]);

    $result = $this->searchService->search('SUMMER', ['type' => 'voucher']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('voucher')
        ->and($result['results'][0]['title'])->toBe('SUMMER-DEAL-50');
});

it('searches warranties and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'warranty',
        'processing_type' => 'warranty',
    ]);

    Warranty::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'product_name' => 'Samsung Galaxy S24',
        'manufacturer' => 'Samsung',
    ]);

    $result = $this->searchService->search('Samsung', ['type' => 'warranty']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('warranty')
        ->and($result['results'][0]['title'])->toBe('Samsung Galaxy S24');
});

it('searches return policies and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'return_policy',
        'processing_type' => 'return_policy',
    ]);

    ReturnPolicy::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'conditions' => 'Full refund within 30 days',
        'refund_method' => 'original_payment',
    ]);

    // Search by refund_method which is in toSearchableArray
    $result = $this->searchService->search('original_payment', ['type' => 'return_policy']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('return_policy');
});

it('searches bank statements and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'bank_statement',
        'processing_type' => 'bank_statement',
    ]);

    BankStatement::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'bank_name' => 'DNB Bank ASA',
        'closing_balance' => 25000.00,
        'currency' => 'NOK',
    ]);

    $result = $this->searchService->search('DNB', ['type' => 'bank_statement']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('bank_statement')
        ->and($result['results'][0]['title'])->toBe('DNB Bank ASA');
});

// ─── Type filtering isolates correctly ───────────────────────────

it('type filter excludes other entity types', function () {
    $invoiceFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $invoiceFile->id,
        'invoice_number' => 'FILTERTEST-001',
    ]);

    $contractFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'contract',
        'processing_type' => 'contract',
    ]);
    Contract::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $contractFile->id,
        'contract_title' => 'FILTERTEST Agreement',
    ]);

    // Searching with type=invoice should not return contracts
    $result = $this->searchService->search('FILTERTEST', ['type' => 'invoice']);
    $types = array_column($result['results'], 'type');

    expect($types)->not->toBeEmpty()
        ->and($types)->each->toBe('invoice');
});

// ─── Facets include all types ────────────────────────────────────

it('facets include counts for all entity types', function () {
    $invoiceFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $invoiceFile->id,
        'from_name' => 'Facet Test Company',
    ]);

    $contractFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'contract',
        'processing_type' => 'contract',
    ]);
    Contract::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $contractFile->id,
        'contract_title' => 'Facet Test Contract',
    ]);

    $result = $this->searchService->search('Facet');

    expect($result['facets'])->toHaveKeys([
        'total', 'receipts', 'documents', 'invoices', 'contracts',
        'vouchers', 'warranties', 'return_policies', 'bank_statements',
    ]);
});

// ─── All types appear in unfiltered search ───────────────────────

it('all type results appear in unfiltered search', function () {
    $invoiceFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $invoiceFile->id,
        'invoice_number' => 'XUNIQ-77777',
    ]);

    $contractFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'contract',
        'processing_type' => 'contract',
    ]);
    Contract::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $contractFile->id,
        'contract_title' => 'XUNIQ Agreement',
    ]);

    // Search for something that matches both indexed fields
    $result = $this->searchService->search('XUNIQ', ['type' => 'all']);
    $types = array_unique(array_column($result['results'], 'type'));

    expect($types)->toContain('invoice')
        ->and($types)->toContain('contract');
});

// ─── User isolation ──────────────────────────────────────────────

it('does not return other users entities', function () {
    $otherUser = User::factory()->create();

    $file = File::factory()->create([
        'user_id' => $otherUser->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);
    Invoice::factory()->create([
        'user_id' => $otherUser->id,
        'file_id' => $file->id,
        'invoice_number' => 'OTHERUSER-999',
    ]);

    $result = $this->searchService->search('OTHERUSER-999', ['type' => 'invoice']);

    expect($result['results'])->toBeEmpty();
});

// ─── API validation ──────────────────────────────────────────────

it('api search accepts new entity types as type filter', function () {
    $this->actingAs($this->user);

    $types = ['invoice', 'contract', 'voucher', 'warranty', 'return_policy', 'bank_statement'];

    foreach ($types as $type) {
        $response = $this->getJson('/api/v1/search?q=test&type='.$type);
        $response->assertSuccessful();
    }
});

it('api search rejects invalid type filter', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/api/v1/search?q=test&type=invalid_type');
    $response->assertStatus(422);
});
