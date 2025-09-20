<?php

namespace App\Services\PulseDav;

use App\Contracts\Services\PulseDavImportContract;
use App\Models\PulseDavFile;
use App\Models\PulseDavImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PulseDavImportService implements PulseDavImportContract
{
    protected PulseDavFolderService $folderService;

    public function __construct(PulseDavFolderService $folderService)
    {
        $this->folderService = $folderService;
    }

    /**
     * Import selected files/folders with tags
     */
    public function importSelections(User $user, array $selections, array $options = []): array
    {
        Log::info('[PulseDavImport] Import started', [
            'user_id' => $user->id,
            'selections_count' => count($selections),
            'selections' => $selections,
            'options' => $options,
        ]);

        $fileType = $options['file_type'] ?? 'receipt';
        $tagIds = $options['tag_ids'] ?? [];
        $notes = $options['notes'] ?? null;

        // Create import batch
        $batch = PulseDavImportBatch::create([
            'user_id' => $user->id,
            'imported_at' => now(),
            'file_count' => 0,
            'tag_ids' => $tagIds,
            'notes' => $notes,
        ]);

        Log::info('[PulseDavImport] Import batch created', [
            'batch_id' => $batch->id,
        ]);

        $imported = 0;
        $skipped = 0;

        foreach ($selections as $selection) {
            Log::debug('[PulseDavImport] Processing selection', [
                'selection' => $selection,
                's3_path' => $selection['s3_path'],
            ]);

            // Get the file/folder from database
            $item = PulseDavFile::where('user_id', $user->id)
                ->where('s3_path', $selection['s3_path'])
                ->first();

            if (! $item) {
                Log::warning('[PulseDavImport] Item not found in database', [
                    's3_path' => $selection['s3_path'],
                    'user_id' => $user->id,
                ]);
                $skipped++;

                continue;
            }

            Log::info('[PulseDavImport] Found item', [
                'id' => $item->id,
                'filename' => $item->filename,
                'is_folder' => $item->is_folder,
                'status' => $item->status,
            ]);

            if ($item->is_folder) {
                // Import all files in folder
                $folderImportCount = $this->importFolder($item, $batch, $fileType, $tagIds);
                $imported += $folderImportCount;
            } else {
                // Import single file
                if ($item->isProcessable()) {
                    Log::info('[PulseDavImport] Importing single file', [
                        'file_id' => $item->id,
                        'status' => $item->status,
                    ]);
                    $this->importFile($item, $batch, $fileType, $tagIds);
                    $imported++;
                } else {
                    Log::warning('[PulseDavImport] File not processable', [
                        'file_id' => $item->id,
                        'status' => $item->status,
                    ]);
                    $skipped++;
                }
            }
        }

        // Update batch file count
        $batch->update(['file_count' => $imported]);

        Log::info('[PulseDavImport] Import completed', [
            'batch_id' => $batch->id,
            'imported' => $imported,
            'skipped' => $skipped,
        ]);

        return [
            'batch_id' => $batch->id,
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    /**
     * Import all files in a folder
     */
    protected function importFolder(PulseDavFile $folder, PulseDavImportBatch $batch, string $fileType, array $tagIds): int
    {
        $files = PulseDavFile::where('user_id', $folder->user_id)
            ->where('folder_path', 'like', $folder->folder_path.'%')
            ->filesOnly()
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        Log::info('[PulseDavImport] Processing folder', [
            'folder_path' => $folder->folder_path,
            'files_count' => $files->count(),
        ]);

        $imported = 0;
        foreach ($files as $file) {
            $this->importFile($file, $batch, $fileType, $tagIds);
            $imported++;
        }

        return $imported;
    }

    /**
     * Import a single file
     */
    protected function importFile(PulseDavFile $file, PulseDavImportBatch $batch, string $fileType, array $tagIds): void
    {
        Log::info('[PulseDavImport] Importing file', [
            'file_id' => $file->id,
            'filename' => $file->filename,
            's3_path' => $file->s3_path,
            'file_type' => $fileType,
            'tag_ids' => $tagIds,
        ]);

        // Get inherited tags from folders
        $inheritedTags = $file->inherited_tags->pluck('id')->toArray();
        $allTagIds = array_unique(array_merge($tagIds, $inheritedTags));

        Log::debug('[PulseDavImport] Tag inheritance', [
            'direct_tags' => $tagIds,
            'inherited_tags' => $inheritedTags,
            'all_tags' => $allTagIds,
        ]);

        // Update file with import info
        $file->update([
            'file_type' => $fileType,
            'import_batch_id' => $batch->id,
            'status' => 'processing',
        ]);

        Log::info('[PulseDavImport] Dispatching ProcessPulseDavFile job', [
            'file_id' => $file->id,
            'all_tag_ids' => $allTagIds,
        ]);

        // Dispatch processing job with tags
        \App\Jobs\PulseDav\ProcessPulseDavFile::dispatch($file, $allTagIds)
            ->onQueue('default');
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

            // Get the original tags from the batch
            $tagIds = $batch->tag_ids ?? [];
            $inheritedTags = $file->inherited_tags->pluck('id')->toArray();
            $allTagIds = array_unique(array_merge($tagIds, $inheritedTags));

            \App\Jobs\PulseDav\ProcessPulseDavFile::dispatch($file, $allTagIds)
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
