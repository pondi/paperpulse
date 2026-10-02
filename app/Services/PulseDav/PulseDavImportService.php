<?php

namespace App\Services\PulseDav;

use App\Contracts\Services\PulseDavImportContract;
use App\Models\PulseDavFile;
use App\Models\PulseDavImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PulseDavImportService implements PulseDavImportContract
{
    /**
     * Import selected files/folders with tags.
     */
    public function importSelections(User $user, array $selections, array $options = []): array
    {
        return SelectionImportService::importSelected($user, $selections, $options);
    }

    /**
     * Get import batch statistics
     */
    public function getBatchStats(PulseDavImportBatch $batch): array
    {
        $batch->files()->whereIn('status', ['queued', 'processing', 'handed_off'])->chunkById(100, function ($files): void {
            foreach ($files as $file) {
                ImportService::reconcileFile($file);
            }
        });
        $counts = $batch->files()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $stats = [
            'batch_id' => $batch->id, 'total_files' => $counts->sum(),
            'pending_files' => (int) ($counts['pending'] ?? 0) + (int) ($counts['queued'] ?? 0),
            'processing_files' => (int) ($counts['processing'] ?? 0) + (int) ($counts['handed_off'] ?? 0),
            'completed_files' => (int) ($counts['completed'] ?? 0), 'failed_files' => (int) ($counts['failed'] ?? 0),
            'created_receipts' => $batch->files()->whereNotNull('receipt_id')->count(),
            'created_documents' => $batch->files()->whereNotNull('file_id')->where('status', 'completed')->whereNull('receipt_id')->count(),
            'imported_at' => $batch->imported_at, 'tag_ids' => $batch->tag_ids, 'notes' => $batch->notes,
        ];

        return $stats;
    }

    /**
     * Get user's import history
     */
    public function getUserImportHistory(User $user, int $limit = 20): array
    {
        $batches = PulseDavImportBatch::where('user_id', $user->id)
            ->orderBy('imported_at', 'desc')
            ->limit($limit)
            ->get();

        return $batches->map(function ($batch) {
            return $this->getBatchStats($batch);
        })->toArray();
    }

    /**
     * Retry failed imports from a batch
     */
    public function retryFailedImports(PulseDavImportBatch $batch): int
    {
        $failedFiles = PulseDavFile::where('import_batch_id', $batch->id)
            ->where('status', 'failed')
            ->get();

        $retried = 0;
        foreach ($failedFiles as $file) {
            $retried += (int) ImportService::importFile($file, $batch, $file->file_type, $batch->tag_ids ?? [], $batch->notes);

            Log::info('[PulseDavImport] Retrying failed import', [
                'file_id' => $file->id,
                'batch_id' => $batch->id,
            ]);
        }

        Log::info('[PulseDavImport] Retried failed imports', [
            'batch_id' => $batch->id,
            'retried_count' => $retried,
        ]);

        return $retried;
    }

    /**
     * Cancel pending imports from a batch
     */
    public function cancelPendingImports(PulseDavImportBatch $batch): int
    {
        $pendingFiles = PulseDavFile::where('import_batch_id', $batch->id)
            ->whereIn('status', ['pending', 'queued'])
            ->get();

        $cancelled = 0;
        foreach ($pendingFiles as $file) {
            $cancelled += PulseDavFile::query()->whereKey($file->id)->whereIn('status', ['pending', 'queued'])->update([
                'status' => 'pending',
                'import_batch_id' => null,
                'job_id' => null,
                'error_message' => 'Import cancelled by user',
            ]);
        }

        Log::info('[PulseDavImport] Cancelled pending imports', [
            'batch_id' => $batch->id,
            'cancelled_count' => $cancelled,
        ]);

        return $cancelled;
    }

    /**
     * Delete an import batch and reset associated files
     */
    public function deleteBatch(PulseDavImportBatch $batch): int
    {
        $files = PulseDavFile::where('import_batch_id', $batch->id)->get();

        $resetCount = 0;
        foreach ($files as $file) {
            // Reset files that haven't been completed
            if (in_array($file->status, ['pending', 'queued', 'failed'], true)) {
                $file->update([
                    'status' => 'pending',
                    'job_id' => null,
                    'import_batch_id' => null,
                    'error_message' => null,
                ]);
                $resetCount++;
            }
        }

        $batch->delete();

        Log::info('[PulseDavImport] Deleted import batch', [
            'batch_id' => $batch->id,
            'reset_files_count' => $resetCount,
        ]);

        return $resetCount;
    }
}
