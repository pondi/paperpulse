<?php

use App\Enums\DeletedReason;
use App\Models\Document;
use App\Models\File;
use App\Models\Receipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('it permanently deletes user-deleted records older than 30 days', function () {
    // Create a file with a receipt that was soft deleted 31 days ago
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'deleted_reason' => DeletedReason::UserDelete,
    ]);
    $file->delete();
    File::withTrashed()->where('id', $file->id)->update(['deleted_at' => Carbon::now()->subDays(31)]);

    $receipt = Receipt::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'deleted_reason' => DeletedReason::UserDelete,
    ]);
    $receipt->delete();
    Receipt::withTrashed()->where('id', $receipt->id)->update(['deleted_at' => Carbon::now()->subDays(31)]);

    // Verify records exist as soft-deleted
    expect(File::onlyTrashed()->count())->toBe(1);
    expect(Receipt::onlyTrashed()->count())->toBe(1);

    // Run cleanup
    Artisan::call('cleanup:soft-deleted', ['--days' => 30]);

    // Verify records are permanently deleted
    expect(File::onlyTrashed()->count())->toBe(0);
    expect(Receipt::onlyTrashed()->count())->toBe(0);
});

test('it does not delete records newer than specified days', function () {
    // Create a file that was soft deleted 29 days ago (should not be deleted)
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'deleted_reason' => DeletedReason::UserDelete,
    ]);
    $file->delete();
    File::withTrashed()->where('id', $file->id)->update(['deleted_at' => Carbon::now()->subDays(29)]);

    // Run cleanup
    Artisan::call('cleanup:soft-deleted', ['--days' => 30]);

    // Verify record still exists
    expect(File::onlyTrashed()->count())->toBe(1);
});

test('it does not delete reprocess records by default', function () {
    // Create a file that was soft deleted during reprocessing 31 days ago
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'deleted_reason' => DeletedReason::Reprocess,
    ]);
    $file->delete();
    File::withTrashed()->where('id', $file->id)->update(['deleted_at' => Carbon::now()->subDays(31)]);

    // Run cleanup without --include-reprocess flag
    Artisan::call('cleanup:soft-deleted', ['--days' => 30]);

    // Verify record still exists (reprocess records are kept by default)
    expect(File::onlyTrashed()->count())->toBe(1);
});

test('it deletes reprocess records when include-reprocess flag is set', function () {
    // Create a file that was soft deleted during reprocessing 31 days ago
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'deleted_reason' => DeletedReason::Reprocess,
    ]);
    $file->delete();
    File::withTrashed()->where('id', $file->id)->update(['deleted_at' => Carbon::now()->subDays(31)]);

    // Run cleanup with --include-reprocess flag
    Artisan::call('cleanup:soft-deleted', ['--days' => 30, '--include-reprocess' => true]);

    // Verify record is deleted
    expect(File::onlyTrashed()->count())->toBe(0);
});

test('dry run mode does not delete any records', function () {
    // Create a file that should be deleted
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'deleted_reason' => DeletedReason::UserDelete,
    ]);
    $file->delete();
    File::withTrashed()->where('id', $file->id)->update(['deleted_at' => Carbon::now()->subDays(31)]);

    // Run cleanup in dry run mode
    Artisan::call('cleanup:soft-deleted', ['--days' => 30, '--dry-run' => true]);

    // Verify record still exists
    expect(File::onlyTrashed()->count())->toBe(1);
});

test('it deletes account-deleted records', function () {
    // Create a file that was soft deleted due to account deletion 31 days ago
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'deleted_reason' => DeletedReason::AccountDelete,
    ]);
    $file->delete();
    File::withTrashed()->where('id', $file->id)->update(['deleted_at' => Carbon::now()->subDays(31)]);

    // Run cleanup
    Artisan::call('cleanup:soft-deleted', ['--days' => 30]);

    // Verify record is deleted
    expect(File::onlyTrashed()->count())->toBe(0);
});
