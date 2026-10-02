<?php

use App\Models\Contract;
use App\Models\File;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Factories\ContractFactory;
use App\Services\Factories\InvoiceFactory;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->file = File::factory()->create(['user_id' => $this->owner->id]);
});

it('reconciles invoice edits and preserves manual financial fields during replacement', function (string $type, string $status, float $total, float $paid, float $due) {
    $invoice = Invoice::factory()->create(['file_id' => $this->file->id, 'user_id' => $this->owner->id, 'invoice_type' => $type, 'total_amount' => $total, 'amount_paid' => 0, 'payment_status' => 'unpaid']);
    $this->patchJson(route('invoices.update', $invoice), ['total_amount' => $total, 'payment_status' => $status, 'amount_paid' => $paid])->assertRedirect();
    expect((float) $invoice->fresh()->amount_due)->toBe($due)->and((float) $invoice->fresh()->amount_paid)->toBe($paid);
    $replacement = app(InvoiceFactory::class)->create(['merchant_id' => null, 'currency' => 'NOK', 'total_amount' => 999, 'payment_status' => 'unpaid'], $this->file->fresh());
    expect((float) $replacement->total_amount)->toBe($total)->and((float) $replacement->amount_due)->toBe($due)->and($replacement->payment_status)->toBe($status);
})->with([
    ['invoice', 'paid', 100.0, 100.0, 0.0],
    ['invoice', 'partial', 100.0, 30.0, 70.0],
    ['invoice', 'unpaid', 100.0, 0.0, 100.0],
    ['credit_note', 'unpaid', -100.0, 0.0, -100.0],
    ['credit_note', 'paid', -100.0, -100.0, 0.0],
]);

it('rejects invalid invoice status sign and partial payment edits', function (array $edit, string $error) {
    $invoice = Invoice::factory()->create(['file_id' => $this->file->id, 'user_id' => $this->owner->id, 'invoice_type' => 'invoice', 'total_amount' => 100, 'amount_paid' => 0, 'payment_status' => 'unpaid']);
    $this->patchJson(route('invoices.update', $invoice), $edit)->assertUnprocessable()->assertJsonValidationErrors($error);
    expect($invoice->fresh()->payment_status)->toBe('unpaid');
})->with([
    [['payment_status' => 'invented'], 'payment_status'],
    [['total_amount' => -10], 'total_amount'],
    [['payment_status' => 'partial', 'amount_paid' => 100], 'amount_paid'],
    [['payment_status' => 'partial', 'amount_paid' => 0], 'amount_paid'],
    [['invoice_type' => 'credit_note'], 'total_amount'],
]);

it('validates contract statuses and preserves manual contract edits during replacement', function () {
    $contract = Contract::factory()->create(['file_id' => $this->file->id, 'user_id' => $this->owner->id, 'status' => 'active']);
    $this->patchJson(route('contracts.update', $contract), ['status' => 'invented'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->patchJson(route('contracts.update', $contract), ['status' => 'terminated', 'summary' => null])->assertRedirect();
    $replacement = app(ContractFactory::class)->create(['status' => 'active', 'summary' => 'AI summary'], $this->file->fresh());
    expect($replacement->status)->toBe('terminated')->and($replacement->summary)->toBeNull();
});

it('forbids foreign invoice edits', function () {
    $other = User::factory()->create();
    $this->actingAs($other);
    $foreignFile = File::factory()->create(['user_id' => $other->id]);
    $foreign = Invoice::factory()->create(['user_id' => $other->id, 'file_id' => $foreignFile->id]);
    $this->actingAs($this->owner);
    $this->patchJson(route('invoices.update', $foreign), ['payment_status' => 'paid'])->assertNotFound();
});
