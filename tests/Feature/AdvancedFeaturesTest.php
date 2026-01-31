<?php

use App\Models\DuplicateFlag;
use App\Models\File;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\DuplicateDetectionService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Duplicate Detection', function () {
    it('detects duplicate files by file hash', function () {
        $service = app(DuplicateDetectionService::class);

        $fileHash = hash('sha256', 'test file content '.uniqid());

        // Create first file
        $file1 = File::factory()->create([
            'user_id' => $this->user->id,
            'file_hash' => $fileHash,
            'status' => 'completed',
        ]);

        // Create second file with same hash
        $file2 = File::factory()->create([
            'user_id' => $this->user->id,
            'file_hash' => $fileHash,
            'status' => 'completed',
        ]);

        // Check for duplicates
        $flags = $service->flagFileHashDuplicates($file2);

        expect($flags)->toHaveCount(1);
        // The service creates a flag linking file2 to file1
        $flag = $flags->first();
        expect($flag->file_id)->toBe($file1->id);
        expect($flag->duplicate_file_id)->toBe($file2->id);
    });

    it('does not flag files from different users as duplicates', function () {
        $service = app(DuplicateDetectionService::class);
        $otherUser = User::factory()->create();

        $fileHash = hash('sha256', 'shared file content');

        // User 1 uploads
        File::factory()->create([
            'user_id' => $this->user->id,
            'file_hash' => $fileHash,
            'status' => 'completed',
        ]);

        // User 2 uploads same file - should not be flagged
        $file2 = File::factory()->create([
            'user_id' => $otherUser->id,
            'file_hash' => $fileHash,
            'status' => 'completed',
        ]);

        $flags = $service->flagFileHashDuplicates($file2);
        expect($flags)->toHaveCount(0);
    });

    it('creates duplicate flags in database', function () {
        $service = app(DuplicateDetectionService::class);

        $fileHash = hash('sha256', 'test content');

        $file1 = File::factory()->create([
            'user_id' => $this->user->id,
            'file_hash' => $fileHash,
        ]);

        $file2 = File::factory()->create([
            'user_id' => $this->user->id,
            'file_hash' => $fileHash,
        ]);

        $flags = $service->flagFileHashDuplicates($file2);

        // The service sorts IDs, so primaryId is smaller, secondaryId is larger
        [$primaryId, $secondaryId] = $file1->id < $file2->id ? [$file1->id, $file2->id] : [$file2->id, $file1->id];

        // Verify the flag was created
        expect($flags->count())->toBe(1);

        $flag = DuplicateFlag::where('user_id', $this->user->id)
            ->where('file_id', $primaryId)
            ->where('duplicate_file_id', $secondaryId)
            ->first();

        expect($flag)->not()->toBeNull();
        expect($flag->reason)->toBe('hash_match');
        expect($flag->status)->toBe('open');
    });
});

describe('Notification System', function () {
    it('creates notification history for expiring vouchers', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);

        $voucher = Voucher::factory()->create([
            'file_id' => $file->id,
            'user_id' => $this->user->id,
            'expiry_date' => now()->addDays(15),
            'is_redeemed' => false,
        ]);

        $this->artisan('notify:expiring-vouchers --days=30');

        $this->assertDatabaseHas('notification_history', [
            'user_id' => $this->user->id,
            'notification_type' => 'voucher_expiring',
            'entity_type' => 'voucher',
            'entity_id' => $voucher->id,
        ]);
    });

    it('does not send duplicate notifications', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);

        $voucher = Voucher::factory()->create([
            'file_id' => $file->id,
            'user_id' => $this->user->id,
            'expiry_date' => now()->addDays(15),
            'is_redeemed' => false,
        ]);

        // First notification
        $this->artisan('notify:expiring-vouchers --days=30')
            ->expectsOutput('Sent 1 voucher expiring notifications.');

        // Second notification - should skip
        $this->artisan('notify:expiring-vouchers --days=30')
            ->expectsOutput('Skipped 1 vouchers (preferences, missing user, or already notified).');
    });

    it('creates notification history for expiring warranties', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);

        $warranty = Warranty::factory()->create([
            'file_id' => $file->id,
            'user_id' => $this->user->id,
            'warranty_end_date' => now()->addDays(20),
        ]);

        $this->artisan('notify:expiring-warranties --days=30');

        $this->assertDatabaseHas('notification_history', [
            'user_id' => $this->user->id,
            'notification_type' => 'warranty_ending',
            'entity_type' => 'warranty',
            'entity_id' => $warranty->id,
        ]);
    });
});

describe('Duplicate Detection UI', function () {
    it('lists duplicate files via web route', function () {
        $service = app(DuplicateDetectionService::class);
        $fileHash = hash('sha256', 'duplicate content');

        $file1 = File::factory()->create([
            'user_id' => $this->user->id,
            'file_hash' => $fileHash,
            'status' => 'completed',
        ]);

        $file2 = File::factory()->create([
            'user_id' => $this->user->id,
            'file_hash' => $fileHash,
            'status' => 'completed',
        ]);

        // Create the duplicate flags
        $service->flagFileHashDuplicates($file2);

        $response = $this->get('/duplicates');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $page->component('Duplicates/Index')
                ->has('duplicates');
        });
    });
});
