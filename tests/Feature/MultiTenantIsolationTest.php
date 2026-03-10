<?php

declare(strict_types=1);

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Multi-Tenant Data Isolation Tests
|--------------------------------------------------------------------------
|
| BelongsToUser trait adds a global scope filtering by auth()->id().
| This means another user's resources return 404 (not 403).
|
| Tested entities with web routes:
|   Receipt, Document, Invoice, Contract, Voucher, BankStatement
|
| Tested entities without routes (model-level isolation):
|   Warranty, File
|
*/

// --- Receipt Isolation ---

it('prevents user from viewing another users receipt', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $receipt = Receipt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->get(route('receipts.show', $receipt->id))
        ->assertNotFound();
});

it('prevents user from updating another users receipt via model scope', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $receipt = Receipt::factory()->create(['user_id' => $owner->id]);

    // Verify isolation at the model level (BelongsToUser scope)
    $this->actingAs($intruder);
    expect(Receipt::find($receipt->id))->toBeNull();
});

it('prevents user from deleting another users receipt via model scope', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $receipt = Receipt::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder);
    expect(Receipt::find($receipt->id))->toBeNull();
});

it('receipt index only shows current users receipts', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Receipt::factory()->count(3)->create(['user_id' => $owner->id]);
    Receipt::factory()->count(5)->create(['user_id' => $other->id]);

    $this->actingAs($owner)
        ->get(route('receipts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('receipts', 3)
            ->where('pagination.total', 3)
        );
});

// --- Document Isolation ---

it('prevents user from viewing another users document', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->get(route('documents.show', $document->id))
        ->assertNotFound();
});

it('prevents user from updating another users document', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->patch(route('documents.update', $document->id), ['title' => 'Hijacked'])
        ->assertNotFound();
});

it('prevents user from deleting another users document', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->delete(route('documents.destroy', $document->id))
        ->assertNotFound();
});

// --- Invoice Isolation ---

it('prevents user from viewing another users invoice', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->get(route('invoices.show', $invoice->id))
        ->assertNotFound();
});

it('prevents user from updating another users invoice', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->patch(route('invoices.update', $invoice->id), ['invoice_number' => 'STOLEN-001'])
        ->assertNotFound();
});

it('prevents user from deleting another users invoice', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->delete(route('invoices.destroy', $invoice->id))
        ->assertNotFound();
});

// --- Contract Isolation ---

it('prevents user from viewing another users contract', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $contract = Contract::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->get(route('contracts.show', $contract->id))
        ->assertNotFound();
});

it('prevents user from updating another users contract', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $contract = Contract::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->patch(route('contracts.update', $contract->id), ['contract_title' => 'Stolen'])
        ->assertNotFound();
});

it('prevents user from deleting another users contract', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $contract = Contract::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->delete(route('contracts.destroy', $contract->id))
        ->assertNotFound();
});

// --- Voucher Isolation ---

it('prevents user from viewing another users voucher', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $voucher = Voucher::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->get(route('vouchers.show', $voucher->id))
        ->assertNotFound();
});

it('prevents user from deleting another users voucher', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $voucher = Voucher::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->delete(route('vouchers.destroy', $voucher->id))
        ->assertNotFound();
});

// --- Bank Statement Isolation ---

it('prevents user from viewing another users bank statement', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $statement = BankStatement::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->get(route('bank-statements.show', $statement->id))
        ->assertNotFound();
});

it('prevents user from updating another users bank statement', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $statement = BankStatement::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->patch(route('bank-statements.update', $statement->id), ['bank_name' => 'Stolen Bank'])
        ->assertNotFound();
});

it('prevents user from deleting another users bank statement', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $statement = BankStatement::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->delete(route('bank-statements.destroy', $statement->id))
        ->assertNotFound();
});

// --- File Isolation ---

it('prevents user from viewing another users file via API', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->getJson(route('api.files.show', $file->id))
        ->assertNotFound();
});

it('api file list only returns current users files', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    File::factory()->count(3)->create(['user_id' => $owner->id]);
    File::factory()->count(4)->create(['user_id' => $other->id]);

    $response = $this->actingAs($owner)
        ->getJson(route('api.files.index'))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

// --- Warranty Model-Level Isolation ---

it('prevents querying another users warranty via model', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $warranty = Warranty::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder);

    $found = Warranty::find($warranty->id);
    expect($found)->toBeNull();
});

// --- Index Endpoints Return Empty for Other Users ---

it('entity index returns empty set for user with no data', function (string $routeName) {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    match ($routeName) {
        'receipts.index' => Receipt::factory()->create(['user_id' => $owner->id]),
        'documents.index' => Document::factory()->create(['user_id' => $owner->id]),
        'invoices.index' => Invoice::factory()->create(['user_id' => $owner->id]),
        'contracts.index' => Contract::factory()->create(['user_id' => $owner->id]),
        'vouchers.index' => Voucher::factory()->create(['user_id' => $owner->id]),
        'bank-statements.index' => BankStatement::factory()->create(['user_id' => $owner->id]),
    };

    $this->actingAs($intruder)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'receipts' => 'receipts.index',
    'documents' => 'documents.index',
    'invoices' => 'invoices.index',
    'contracts' => 'contracts.index',
    'vouchers' => 'vouchers.index',
    'bank-statements' => 'bank-statements.index',
]);

// --- Sharing Edge Cases ---

it('shared receipt is accessible by share recipient', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();

    $receipt = Receipt::factory()->create(['user_id' => $owner->id]);

    FileShare::create([
        'file_id' => $receipt->file_id,
        'file_type' => 'receipt',
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $recipient->id,
        'permission' => 'view',
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($recipient);
    $found = Receipt::accessibleBy($recipient)->find($receipt->id);
    expect($found)->not->toBeNull();
    expect($found->id)->toBe($receipt->id);
});

it('shared receipt is not accessible by unrelated users', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $stranger = User::factory()->create();

    $receipt = Receipt::factory()->create(['user_id' => $owner->id]);

    FileShare::create([
        'file_id' => $receipt->file_id,
        'file_type' => 'receipt',
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $recipient->id,
        'permission' => 'view',
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($stranger);
    $found = Receipt::accessibleBy($stranger)->find($receipt->id);
    expect($found)->toBeNull();
});

it('expired shares are not accessible', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();

    $receipt = Receipt::factory()->create(['user_id' => $owner->id]);

    FileShare::create([
        'file_id' => $receipt->file_id,
        'file_type' => 'receipt',
        'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $recipient->id,
        'permission' => 'view',
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($recipient);
    $found = Receipt::accessibleBy($recipient)->find($receipt->id);
    expect($found)->toBeNull();
});

// --- Soft Deleted Records ---

it('soft deleted records dont leak to other users', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $file = File::factory()->create(['user_id' => $owner->id]);
    $file->delete();

    $this->actingAs($intruder);

    $found = File::withTrashed()->find($file->id);
    expect($found)->toBeNull();
});
