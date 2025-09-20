<?php

namespace App\Console\Commands;

use App\Services\Receipts\Cleanup\DuplicateReceiptCleaner;
use App\Services\Receipts\Cleanup\DuplicateReceiptIdentifier;
use Illuminate\Console\Command;

class CleanupDuplicateReceipts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'receipts:cleanup-duplicates 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--file-id= : Clean duplicates for a specific file ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up duplicate receipts in the system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fileIdOption = $this->option('file-id');
        $fileId = $fileIdOption !== null ? (int) $fileIdOption : null;

        $this->info('Starting duplicate receipt cleanup...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        if ($fileId !== null) {
            $this->cleanFileReceipts((int) $fileId, $dryRun);
        } else {
            $this->cleanAllReceipts($dryRun);
        }

        return Command::SUCCESS;
    }

    private function cleanFileReceipts(int $fileId, bool $dryRun): void
    {
        $duplicates = DuplicateReceiptIdentifier::findDuplicatesForFile($fileId);

        if ($duplicates->count() <= 1) {
            $this->info("No duplicates found for file ID: $fileId");

            return;
        }

        $this->info("Found {$duplicates->count()} receipts for file ID: $fileId");

        if (! $dryRun) {
            $result = DuplicateReceiptCleaner::cleanForFile($fileId);
            $this->info("Deleted {$result['receipts_deleted']} duplicate(s), kept receipt ID: {$result['kept_receipt_id']}");
        }
    }

    private function cleanAllReceipts(bool $dryRun): void
    {
        $duplicateGroups = DuplicateReceiptIdentifier::findDuplicates();

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate receipts found in the system.');

            return;
        }

        $totalFiles = $duplicateGroups->count();
        $totalDuplicates = $duplicateGroups->sum(fn ($group) => $group->count() - 1);

        $this->info("Found duplicates for $totalFiles file(s) with $totalDuplicates duplicate receipt(s) total");

        if (! $dryRun) {
            $results = DuplicateReceiptCleaner::cleanAll();
            $totalDeleted = collect($results)->sum('receipts_deleted');
            $this->info("Successfully deleted $totalDeleted duplicate receipt(s)");
        }
    }
}
