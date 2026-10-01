<?php

namespace App\Console\Commands;

use App\Enums\DeletedReason;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Collection;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Vendor;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\Files\FileCleanupService;
use App\Services\Files\FileDeletionService;
use App\Services\StorageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupSoftDeletedRecords extends Command
{
    protected $signature = 'cleanup:soft-deleted
                            {--days=30 : Number of days after soft delete before permanent deletion}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--include-reprocess : Also delete records soft-deleted during reprocessing}';

    protected $description = 'Permanently delete soft-deleted records older than specified days and clean up S3 files';

    protected StorageService $storageService;

    protected int $deletedCount = 0;

    protected int $s3FilesDeleted = 0;

    protected bool $dryRun = false;

    public function __construct(StorageService $storageService)
    {
        parent::__construct();
        $this->storageService = $storageService;
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $this->dryRun = (bool) $this->option('dry-run');
        $includeReprocess = (bool) $this->option('include-reprocess');

        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Cleaning up soft-deleted records older than {$days} days (before {$cutoffDate->toDateString()})");

        if ($this->dryRun) {
            $this->warn('DRY RUN MODE - No records will actually be deleted');
        }

        if (! $this->dryRun) {
            app(FileDeletionService::class)->backfillLegacyDeletions();
        }

        // Build the deleted reasons to include
        $reasons = [DeletedReason::UserDelete, DeletedReason::AccountDelete];
        if ($includeReprocess) {
            $reasons[] = DeletedReason::Reprocess;
        }

        if (! $this->dryRun) {
            File::withoutGlobalScope('user')->onlyTrashed()->where('deleted_at', '<', $cutoffDate)
                ->whereIn('deleted_reason', $reasons)->each(function (File $file) use ($days): void {
                    $service = app(FileDeletionService::class);
                    if ($file->deleted_reason === DeletedReason::Reprocess && $service->hasActiveEntities($file)) {
                        return;
                    }
                    $service->deleteFile($file, $file->user_id, $file->deleted_reason);
                    FileCleanupManifest::query()->where('file_id', $file->id)
                        ->update(['available_at' => $file->deleted_at->copy()->addDays($days)]);
                });
            FileCleanupManifest::query()->whereNull('completed_at')->each(function ($manifest): void {
                $result = app(FileCleanupService::class)->process($manifest);
                $this->s3FilesDeleted += $result['objects_deleted'];
                if ($result['failed']) {
                    $this->warn("  Cleanup remains pending for file {$manifest->file_id}");
                }
            });
        }

        // Clean up in order of dependencies (child records first)
        $this->cleanupModel(LineItem::class, $cutoffDate, $reasons, 'line items');
        $this->cleanupModel(BankTransaction::class, $cutoffDate, $reasons, 'bank transactions');
        $this->cleanupModel(InvoiceLineItem::class, $cutoffDate, $reasons, 'invoice line items');
        $this->cleanupModel(Receipt::class, $cutoffDate, $reasons, 'receipts');
        $this->cleanupModel(Document::class, $cutoffDate, $reasons, 'documents');
        $this->cleanupModel(Invoice::class, $cutoffDate, $reasons, 'invoices');
        $this->cleanupModel(Contract::class, $cutoffDate, $reasons, 'contracts');
        $this->cleanupModel(Voucher::class, $cutoffDate, $reasons, 'vouchers');
        $this->cleanupModel(Warranty::class, $cutoffDate, $reasons, 'warranties');
        $this->cleanupModel(ReturnPolicy::class, $cutoffDate, $reasons, 'return policies');
        $this->cleanupModel(BankStatement::class, $cutoffDate, $reasons, 'bank statements');
        $this->cleanupModel(ExtractableEntity::class, $cutoffDate, $reasons, 'extractable entities');

        // Clean up files (also deletes from S3)
        // Note: FileShare and CollectionShare are cleaned up via foreign key cascade when files/collections are deleted
        $this->cleanupFiles($cutoffDate, $reasons);

        // Clean up parent models
        $this->cleanupModel(Merchant::class, $cutoffDate, $reasons, 'merchants');
        $this->cleanupModel(Vendor::class, $cutoffDate, $reasons, 'vendors');
        $this->cleanupModel(Collection::class, $cutoffDate, $reasons, 'collections');

        $this->newLine();
        $this->info("Cleanup complete: {$this->deletedCount} records permanently deleted, {$this->s3FilesDeleted} S3 files removed");

        return Command::SUCCESS;
    }

    /**
     * Clean up soft-deleted records for a model.
     */
    protected function cleanupModel(string $modelClass, Carbon $cutoffDate, array $reasons, string $label): void
    {
        $query = $modelClass::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->whereIn('deleted_reason', $reasons);

        $pending = app(FileCleanupService::class)->pendingSearchRecords();
        if (isset($pending[$modelClass])) {
            $query->whereNotIn('id', $pending[$modelClass]);
        }

        $count = $query->count();

        if ($count === 0) {
            return;
        }

        $this->line("  - Found {$count} {$label} to delete");

        if ($this->dryRun) {
            return;
        }

        try {
            DB::transaction(function () use ($query, &$count) {
                $query->forceDelete();
            });

            $this->deletedCount += $count;
            Log::info("[CleanupSoftDeleted] Permanently deleted {$count} {$label}");
        } catch (Exception $e) {
            $this->error("  Failed to delete {$label}: {$e->getMessage()}");
            Log::error("[CleanupSoftDeleted] Failed to delete {$label}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up soft-deleted files and their S3 storage.
     */
    protected function cleanupFiles(Carbon $cutoffDate, array $reasons): void
    {
        $files = File::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->whereIn('deleted_reason', $reasons)
            ->get();

        $count = $files->count();

        if ($count === 0) {
            return;
        }

        $this->line("  - Found {$count} files to delete");

        if ($this->dryRun) {
            foreach ($files as $file) {
                $this->line("    Would delete S3 paths for file {$file->id}: {$file->s3_original_path}");
            }

            return;
        }

        foreach ($files as $file) {
            try {
                DB::transaction(function () use ($file) {
                    $manifest = FileCleanupManifest::query()->where('file_id', $file->id)->first();
                    if (! $manifest?->completed_at) {
                        throw new \RuntimeException('File cleanup has not completed.');
                    }

                    $file->forceDelete();
                });

                $this->deletedCount++;
                Log::info('[CleanupSoftDeleted] Permanently deleted file', [
                    'file_id' => $file->id,
                    'file_guid' => $file->guid,
                ]);
            } catch (Exception $e) {
                $this->error("  Failed to delete file {$file->id}: {$e->getMessage()}");
                Log::error('[CleanupSoftDeleted] Failed to delete file', [
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
