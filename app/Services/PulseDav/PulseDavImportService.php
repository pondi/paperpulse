<?php

namespace App\Services\PulseDav;

use App\Contracts\Services\PulseDavImportContract;
use App\Jobs\PulseDav\ProcessPulseDavFile;
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
        $files = PulseDavFile::where('import_batch_id', $batch->id)->get();

        $stats = [
            'batch_id' => $batch->id,
            'total_files' => $files->count(),
            'pending_files' => $files->where('status', 'pending')->count(),
            'processing_files' => $files->where('status', 'processing')->count(),
            'completed_files' => $files->where('status', 'completed')->count(),
            'failed_files' => $files->where('status', 'failed')->count(),
            'created_receipts' => $files->whereNotNull('receipt_id')->count(),
            'created_documents' => $files->whereNotNull('document_id')->count(),
            'imported_at' => $batch->imported_at,
            'tag_ids' => $batch->tag_ids,
            'notes' => $batch->notes,
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
            // Reset status and dispatch job again
            $file->update([
                'status' => 'processing',
                'error_message' => null,
            ]);

            // Get the original tags and note from the batch
            $tagIds = $batch->tag_ids ?? [];
            $inheritedTags = $file->inherited_tags->pluck('id')->toArray();
            $allTagIds = array_unique(array_merge($tagIds, $inheritedTags));

            ProcessPulseDavFile::dispatch($file, $allTagIds, $batch->notes)
                ->onQueue('default');

            $retried++;

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
            ->whereIn('status', ['pending', 'processing'])
            ->get();

        $cancelled = 0;
        foreach ($pendingFiles as $file) {
            $file->update([
                'status' => 'pending',
                'import_batch_id' => null,
                'error_message' => 'Import cancelled by user',
            ]);
            $cancelled++;
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
            if (in_array($file->status, ['pending', 'processing', 'failed'])) {
                $file->update([
                    'status' => 'pending',
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
