<?php

use App\Enums\DeletedReason;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\LineItem;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Files\FileEntityCleanupService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->cleanupService = app(FileEntityCleanupService::class);
});

test('it soft deletes receipt and line items for a file', function () {
    // Create a file with a receipt and line items
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
        'status' => 'completed',
    ]);

    $receipt = Receipt::factory()->create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
    ]);

    // Create line items manually since there's no factory
    $lineItem1 = LineItem::create([
        'receipt_id' => $receipt->id,
        'text' => 'Item 1',
        'qty' => 2,
        'price' => 10.00,
    ]);

    $lineItem2 = LineItem::create([
        'receipt_id' => $receipt->id,
        'text' => 'Item 2',
        'qty' => 1,
        'price' => 25.00,
    ]);

    // Create ExtractableEntity junction record
    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'entity_type' => Receipt::class,
        'entity_id' => $receipt->id,
        'is_primary' => true,
        'extraction_provider' => 'gemini',
        'extracted_at' => now(),
    ]);

    // Verify entities exist
    expect(Receipt::count())->toBe(1);
    expect(LineItem::count())->toBe(2);
    expect(ExtractableEntity::count())->toBe(1);

    // Run cleanup
    $result = $this->cleanupService->softDeleteAndUnindexEntities($file);

    // Verify entities are soft-deleted
    expect(Receipt::count())->toBe(0);
    expect(LineItem::count())->toBe(0);
    expect(ExtractableEntity::count())->toBe(0);

    // Verify soft-deleted records exist
    expect(Receipt::onlyTrashed()->count())->toBe(1);
    expect(LineItem::onlyTrashed()->count())->toBe(2);
    expect(ExtractableEntity::onlyTrashed()->count())->toBe(1);

    // Verify deleted_reason is set correctly
    expect(Receipt::onlyTrashed()->first()->deleted_reason)->toBe(DeletedReason::Reprocess);
    expect(LineItem::onlyTrashed()->first()->deleted_reason)->toBe(DeletedReason::Reprocess);
    expect(ExtractableEntity::onlyTrashed()->first()->deleted_reason)->toBe(DeletedReason::Reprocess);

    // Verify result contains entity info
    expect($result['count'])->toBeGreaterThan(0);
    expect($result['entities'])->toBeArray();
});

test('it soft deletes document for a file', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'document',
        'status' => 'completed',
    ]);

    $document = Document::factory()->create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
    ]);

    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'entity_type' => Document::class,
        'entity_id' => $document->id,
        'is_primary' => true,
        'extraction_provider' => 'gemini',
        'extracted_at' => now(),
    ]);

    expect(Document::count())->toBe(1);

    $result = $this->cleanupService->softDeleteAndUnindexEntities($file);

    expect(Document::count())->toBe(0);
    expect(Document::onlyTrashed()->count())->toBe(1);
    expect(Document::onlyTrashed()->first()->deleted_reason)->toBe(DeletedReason::Reprocess);
});

test('it soft deletes invoice and invoice line items for a file', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'document',
        'status' => 'completed',
    ]);

    $invoice = Invoice::factory()->create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
    ]);

    $lineItem = InvoiceLineItem::factory()->create([
        'invoice_id' => $invoice->id,
    ]);

    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'entity_type' => Invoice::class,
        'entity_id' => $invoice->id,
        'is_primary' => true,
        'extraction_provider' => 'gemini',
        'extracted_at' => now(),
    ]);

    expect(Invoice::count())->toBe(1);
    expect(InvoiceLineItem::count())->toBe(1);

    $this->cleanupService->softDeleteAndUnindexEntities($file);

    expect(Invoice::count())->toBe(0);
    expect(InvoiceLineItem::count())->toBe(0);
    expect(Invoice::onlyTrashed()->count())->toBe(1);
    expect(InvoiceLineItem::onlyTrashed()->count())->toBe(1);
});

test('it soft deletes bank statement and transactions for a file', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'document',
        'status' => 'completed',
    ]);

    $bankStatement = BankStatement::factory()->create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
    ]);

    $transaction = BankTransaction::factory()->create([
        'bank_statement_id' => $bankStatement->id,
    ]);

    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'entity_type' => BankStatement::class,
        'entity_id' => $bankStatement->id,
        'is_primary' => true,
        'extraction_provider' => 'gemini',
        'extracted_at' => now(),
    ]);

    expect(BankStatement::count())->toBe(1);
    expect(BankTransaction::count())->toBe(1);

    $this->cleanupService->softDeleteAndUnindexEntities($file);

    expect(BankStatement::count())->toBe(0);
    expect(BankTransaction::count())->toBe(0);
    expect(BankStatement::onlyTrashed()->count())->toBe(1);
    expect(BankTransaction::onlyTrashed()->count())->toBe(1);
});

test('it hard deletes previously soft-deleted entities', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
        'status' => 'completed',
    ]);

    $receipt = Receipt::factory()->create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
    ]);

    $lineItem = LineItem::create([
        'receipt_id' => $receipt->id,
        'text' => 'Item',
        'qty' => 1,
        'price' => 10.00,
    ]);

    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'entity_type' => Receipt::class,
        'entity_id' => $receipt->id,
        'is_primary' => true,
        'extraction_provider' => 'gemini',
        'extracted_at' => now(),
    ]);

    // Soft delete
    $result = $this->cleanupService->softDeleteAndUnindexEntities($file);

    // Verify soft-deleted
    expect(Receipt::onlyTrashed()->count())->toBe(1);
    expect(LineItem::onlyTrashed()->count())->toBe(1);
    expect(ExtractableEntity::onlyTrashed()->count())->toBe(1);

    // Hard delete
    $this->cleanupService->hardDeleteEntities($result);

    // Verify permanently deleted
    expect(Receipt::withTrashed()->count())->toBe(0);
    expect(LineItem::withTrashed()->count())->toBe(0);
    expect(ExtractableEntity::withTrashed()->count())->toBe(0);
});

test('it handles file with no entities gracefully', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
        'status' => 'pending',
    ]);

    $result = $this->cleanupService->softDeleteAndUnindexEntities($file);

    expect($result['count'])->toBe(0);
    expect($result['entities'])->toBeEmpty();
});

test('hard delete handles empty entity info gracefully', function () {
    // Should not throw an exception
    $this->cleanupService->hardDeleteEntities([]);
    $this->cleanupService->hardDeleteEntities(['entities' => [], 'count' => 0]);

    expect(true)->toBeTrue(); // If we get here, no exception was thrown
});

test('it deletes ALL duplicate receipts for a file', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
        'status' => 'completed',
    ]);

    // Create MULTIPLE receipts for the same file (simulating duplicates from previous reprocessing)
    $receipt1 = Receipt::factory()->create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'total_amount' => 100.00,
    ]);

    $receipt2 = Receipt::factory()->create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'total_amount' => 100.00,
    ]);

    $receipt3 = Receipt::factory()->create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'total_amount' => 100.00,
    ]);

    // Create line items for each receipt
    LineItem::create(['receipt_id' => $receipt1->id, 'text' => 'Item 1', 'qty' => 1, 'price' => 50.00]);
    LineItem::create(['receipt_id' => $receipt2->id, 'text' => 'Item 2', 'qty' => 1, 'price' => 50.00]);
    LineItem::create(['receipt_id' => $receipt3->id, 'text' => 'Item 3', 'qty' => 1, 'price' => 50.00]);

    // Only create ExtractableEntity for ONE receipt (simulating old data)
    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'entity_type' => Receipt::class,
        'entity_id' => $receipt1->id,
        'is_primary' => true,
        'extraction_provider' => 'gemini',
        'extracted_at' => now(),
    ]);

    // Verify we have 3 receipts and 3 line items
    expect(Receipt::where('file_id', $file->id)->count())->toBe(3);
    expect(LineItem::count())->toBe(3);

    // Run cleanup
    $result = $this->cleanupService->softDeleteAndUnindexEntities($file);

    // ALL 3 receipts should be soft-deleted, not just the one with ExtractableEntity
    expect(Receipt::where('file_id', $file->id)->count())->toBe(0);
    expect(Receipt::onlyTrashed()->where('file_id', $file->id)->count())->toBe(3);

    // ALL line items should be soft-deleted
    expect(LineItem::count())->toBe(0);
    expect(LineItem::onlyTrashed()->count())->toBe(3);

    // Verify all have the reprocess reason
    expect(Receipt::onlyTrashed()->where('deleted_reason', DeletedReason::Reprocess)->count())->toBe(3);
});

test('hard delete only deletes entities with reprocess reason', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'receipt',
        'status' => 'completed',
    ]);

    // Create a receipt that was user-deleted (should NOT be hard-deleted)
    $userDeletedReceipt = Receipt::factory()->create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'deleted_reason' => DeletedReason::UserDelete,
    ]);
    $userDeletedReceipt->delete();

    // Create a receipt that will be reprocess-deleted
    $reprocessReceipt = Receipt::factory()->create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
    ]);

    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $this->user->id,
        'entity_type' => Receipt::class,
        'entity_id' => $reprocessReceipt->id,
        'is_primary' => true,
        'extraction_provider' => 'gemini',
        'extracted_at' => now(),
    ]);

    // Soft delete for reprocessing
    $result = $this->cleanupService->softDeleteAndUnindexEntities($file);

    // Hard delete
    $this->cleanupService->hardDeleteEntities($result);

    // User-deleted receipt should still exist (soft-deleted)
    expect(Receipt::onlyTrashed()->where('deleted_reason', DeletedReason::UserDelete)->count())->toBe(1);

    // Reprocess receipt should be permanently deleted
    expect(Receipt::withTrashed()->where('id', $reprocessReceipt->id)->count())->toBe(0);
});
