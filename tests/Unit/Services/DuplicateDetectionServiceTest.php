<?php

declare(strict_types=1);

use App\Models\DuplicateFlag;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DuplicateDetectionService;
    $this->user = User::factory()->create();
});

// --- File Hash Duplicates ---

it('flags files with matching hash', function () {
    $hash = hash('sha256', 'duplicate-content');

    $fileA = File::factory()->create([
        'user_id' => $this->user->id,
        'file_hash' => $hash,
    ]);

    $fileB = File::factory()->create([
        'user_id' => $this->user->id,
        'file_hash' => $hash,
    ]);

    $flags = $this->service->flagFileHashDuplicates($fileA);

    expect($flags)->toHaveCount(1);
    expect($flags->first()->reasons)->toBe(['hash_match']);
});

it('does not flag files with different hashes', function () {
    $fileA = File::factory()->create([
        'user_id' => $this->user->id,
        'file_hash' => hash('sha256', 'content-a'),
    ]);

    File::factory()->create([
        'user_id' => $this->user->id,
        'file_hash' => hash('sha256', 'content-b'),
    ]);

    $flags = $this->service->flagFileHashDuplicates($fileA);

    expect($flags)->toBeEmpty();
});

it('does not flag files from other users', function () {
    $otherUser = User::factory()->create();
    $hash = hash('sha256', 'shared-content');

    $fileA = File::factory()->create([
        'user_id' => $this->user->id,
        'file_hash' => $hash,
    ]);

    File::factory()->create([
        'user_id' => $otherUser->id,
        'file_hash' => $hash,
    ]);

    $flags = $this->service->flagFileHashDuplicates($fileA);

    expect($flags)->toBeEmpty();
});

it('returns empty when file has no hash', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_hash' => null,
    ]);

    $flags = $this->service->flagFileHashDuplicates($file);

    expect($flags)->toBeEmpty();
});

// --- Receipt Duplicates ---

it('flags receipts with matching date and amount', function () {
    $merchant = Merchant::create(['name' => 'Store', 'user_id' => $this->user->id]);

    $receiptA = Receipt::factory()->create([
        'user_id' => $this->user->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 42.50,
        'merchant_id' => $merchant->id,
    ]);

    Receipt::factory()->create([
        'user_id' => $this->user->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 42.50,
        'merchant_id' => $merchant->id,
    ]);

    $flags = $this->service->flagReceiptDuplicates($receiptA);

    expect($flags)->not->toBeEmpty();
});

it('does not flag receipts with only one matching attribute', function () {
    $receiptA = Receipt::factory()->create([
        'user_id' => $this->user->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 42.50,
        'merchant_id' => null,
    ]);

    Receipt::factory()->create([
        'user_id' => $this->user->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 99.99,
        'merchant_id' => null,
    ]);

    $flags = $this->service->flagReceiptDuplicates($receiptA);

    expect($flags)->toBeEmpty();
});

// --- Invoice Duplicates ---

it('flags invoices with matching number and vendor', function () {
    $invoiceA = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'invoice_number' => 'INV-001',
        'from_name' => 'Acme Corp',
        'invoice_date' => '2025-06-15',
        'total_amount' => 1000.00,
    ]);

    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'invoice_number' => 'INV-001',
        'from_name' => 'Acme Corp',
        'invoice_date' => '2025-07-15',
        'total_amount' => 2000.00,
    ]);

    $flags = $this->service->flagInvoiceDuplicates($invoiceA);

    expect($flags)->not->toBeEmpty();
});

// --- Deduplication of Flags ---

it('does not create duplicate flags for same file pair', function () {
    $hash = hash('sha256', 'dup-content');

    $fileA = File::factory()->create([
        'user_id' => $this->user->id,
        'file_hash' => $hash,
    ]);

    $fileB = File::factory()->create([
        'user_id' => $this->user->id,
        'file_hash' => $hash,
    ]);

    $flags1 = $this->service->flagFileHashDuplicates($fileA);
    $flags2 = $this->service->flagFileHashDuplicates($fileA);

    expect($flags1)->toHaveCount(1);
    expect($flags2)->toHaveCount(1);
    expect($flags1->first()->id)->toBe($flags2->first()->id);
});

it('merges multiple reasons into json array without overflow', function () {
    $merchant = Merchant::create(['name' => 'Store', 'user_id' => $this->user->id]);
    $hash = hash('sha256', 'shared-content');

    $receiptA = Receipt::factory()->create([
        'user_id' => $this->user->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 42.50,
        'merchant_id' => $merchant->id,
        'file_id' => File::factory()->create([
            'user_id' => $this->user->id,
            'file_hash' => $hash,
        ])->id,
    ]);

    Receipt::factory()->create([
        'user_id' => $this->user->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 42.50,
        'merchant_id' => $merchant->id,
        'file_id' => File::factory()->create([
            'user_id' => $this->user->id,
            'file_hash' => $hash,
        ])->id,
    ]);

    $fileA = File::find($receiptA->file_id);

    // First: hash match
    $this->service->flagFileHashDuplicates($fileA);
    // Second: receipt match on same file pair
    $this->service->flagReceiptDuplicates($receiptA);

    $flag = DuplicateFlag::where('user_id', $this->user->id)->first();

    expect($flag->reasons)->toBeArray();
    expect($flag->reasons)->toContain('hash_match');
    expect($flag->reasons)->toHaveCount(2);
});
