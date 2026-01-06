<?php

use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Contract;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\ReturnPolicy;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('lists vouchers with filters', function () {
    $voucher = Voucher::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'voucher_type' => 'gift_card',
        'is_redeemed' => true,
    ]);

    Voucher::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'voucher_type' => 'store_credit',
        'is_redeemed' => false,
    ]);

    $response = $this->getJson(route('api.vouchers.index', [
        'voucher_type' => 'gift_card',
        'is_redeemed' => true,
    ]));

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($voucher->id);
});

it('redeems a voucher', function () {
    $voucher = Voucher::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'is_redeemed' => false,
        'expiry_date' => now()->addDays(10),
    ]);

    $response = $this->postJson(route('api.vouchers.redeem', $voucher->id), [
        'redemption_location' => 'Oslo',
    ]);

    $response->assertStatus(200);
    $voucher->refresh();

    expect($voucher->is_redeemed)->toBeTrue();
    expect($voucher->redemption_location)->toBe('Oslo');
});

it('returns not found when accessing another users voucher', function () {
    $otherUser = User::factory()->create();

    $voucher = Voucher::factory()->create([
        'file_id' => createFileForUser($otherUser)->id,
        'user_id' => $otherUser->id,
    ]);

    $response = $this->getJson(route('api.vouchers.show', $voucher->id));

    $response->assertStatus(404);
    $response->assertJsonPath('message', 'Voucher not found');
});

it('lists warranties filtered by manufacturer', function () {
    $warranty = Warranty::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'manufacturer' => 'Acme',
    ]);

    Warranty::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'manufacturer' => 'Other Corp',
    ]);

    $response = $this->getJson(route('api.warranties.index', [
        'manufacturer' => 'Acme',
    ]));

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($warranty->id);
});

it('shows warranty details', function () {
    $warranty = Warranty::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'manufacturer' => 'Acme',
    ]);

    $response = $this->getJson(route('api.warranties.show', $warranty->id));

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($warranty->id);
    expect($response->json('data.manufacturer'))->toBe('Acme');
});

it('lists return policies filtered by refund method', function () {
    $policy = ReturnPolicy::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'refund_method' => 'full_refund',
    ]);

    ReturnPolicy::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'refund_method' => 'exchange_only',
    ]);

    $response = $this->getJson(route('api.return-policies.index', [
        'refund_method' => 'full_refund',
    ]));

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($policy->id);
});

it('shows return policy details', function () {
    $policy = ReturnPolicy::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'refund_method' => 'store_credit',
    ]);

    $response = $this->getJson(route('api.return-policies.show', $policy->id));

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($policy->id);
    expect($response->json('data.refund_method'))->toBe('store_credit');
});

it('filters invoices and includes line items on show', function () {
    $invoice = Invoice::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'payment_status' => 'paid',
    ]);

    Invoice::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'payment_status' => 'unpaid',
    ]);

    InvoiceLineItem::factory()->count(2)->create([
        'invoice_id' => $invoice->id,
    ]);

    $indexResponse = $this->getJson(route('api.invoices.index', [
        'payment_status' => 'paid',
    ]));

    $indexResponse->assertStatus(200);
    $indexResponse->assertJsonCount(1, 'data');
    expect($indexResponse->json('data.0.id'))->toBe($invoice->id);

    $showResponse = $this->getJson(route('api.invoices.show', $invoice->id));

    $showResponse->assertStatus(200);
    expect($showResponse->json('data.line_items'))->toHaveCount(2);
});

it('filters contracts and shows details', function () {
    $contract = Contract::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);

    Contract::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $indexResponse = $this->getJson(route('api.contracts.index', [
        'status' => 'active',
    ]));

    $indexResponse->assertStatus(200);
    $indexResponse->assertJsonCount(1, 'data');
    expect($indexResponse->json('data.0.id'))->toBe($contract->id);

    $showResponse = $this->getJson(route('api.contracts.show', $contract->id));

    $showResponse->assertStatus(200);
    expect($showResponse->json('data.id'))->toBe($contract->id);
});

it('filters bank statements and includes transactions on show', function () {
    $statement = BankStatement::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'bank_name' => 'Nordea',
    ]);

    BankStatement::factory()->create([
        'file_id' => createFileForUser($this->user)->id,
        'user_id' => $this->user->id,
        'bank_name' => 'Other Bank',
    ]);

    BankTransaction::factory()->count(2)->create([
        'bank_statement_id' => $statement->id,
    ]);

    $indexResponse = $this->getJson(route('api.bank-statements.index', [
        'bank_name' => 'Nordea',
    ]));

    $indexResponse->assertStatus(200);
    $indexResponse->assertJsonCount(1, 'data');
    expect($indexResponse->json('data.0.id'))->toBe($statement->id);

    $showResponse = $this->getJson(route('api.bank-statements.show', $statement->id));

    $showResponse->assertStatus(200);
    expect($showResponse->json('data.transactions'))->toHaveCount(2);
});

function createFileForUser(User $user, array $overrides = []): File
{
    return File::factory()->create(array_merge([
        'user_id' => $user->id,
    ], $overrides));
}
