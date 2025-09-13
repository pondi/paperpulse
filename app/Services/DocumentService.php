<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Handles persistence and access of generated documents (PDF/JPG) in storage.
 *
 * Provides helpers to store, retrieve, sign, and delete documents while
 * abstracting over local vs S3-backed disks.
 */
class DocumentService
{
    protected $disk;

    protected $isS3;

    protected FileProcessingService $fileProcessingService;

    protected StorageService $storageService;

    public function __construct(FileProcessingService $fileProcessingService, StorageService $storageService)
    {
        $this->disk = Storage::disk('paperpulse');
        $this->isS3 = config('filesystems.disks.paperpulse.driver') === 's3';
        $this->fileProcessingService = $fileProcessingService;
        $this->storageService = $storageService;
    }


    /**
     * Store a document in the storage system.
     *
     * @param string $content
     * @param string $guid
     * @param string $jobName  For logging context
     * @param string $type     Folder scope, e.g. 'receipts'
     * @param string $extension File extension (pdf|jpg)
     * @return bool
     */
    public function storeDocument(string $content, string $guid, string $jobName, string $type = 'receipts', string $extension = 'pdf'): bool
    {
        try {
            $path = $this->getPath($guid, $type, $extension);
            $success = $this->disk->put($path, $content);

            if (! $success) {
                Log::error('[DocumentService] Document storage failed', [
                    'error' => 'Failed to store document',
                    'guid' => $guid,
                    'type' => $type,
                ]);

                return false;
            }

            Log::info("(DocumentService) [{$jobName}] - Document stored (guid: {$guid})");

            return true;
        } catch (\Exception $e) {
            Log::error('[DocumentService] Document storage error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get a document's content directly.
     *
     * @param string $guid
     * @param string $type
     * @param string $extension
     * @return string|null
     */
    public function getDocument(string $guid, string $type = 'receipts', string $extension = 'pdf')
    {
        try {
            $path = $this->getPath($guid, $type, $extension);

            if (! $this->disk->exists($path)) {
                Log::error('(DocumentService) - Document not found for retrieval', [
                    'guid' => $guid,
                    'type' => $type,
                    'extension' => $extension,
                ]);

                return null;
            }

            return $this->disk->get($path);
        } catch (\Exception $e) {
            Log::error('[DocumentService] Document retrieval error', [
                'error' => $e->getMessage(),
                'document_id' => $guid,
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get a secure URL for accessing the document.
     *
     * @param string $guid
     * @param string $jobName
     * @param string $type
     * @param string $extension
     * @param int $expirationMinutes
     * @return string|null
     */
    public function getSecureUrl(string $guid, string $jobName, string $type = 'receipts', string $extension = 'pdf', int $expirationMinutes = 5): ?string
    {
        try {
            $path = $this->getPath($guid, $type, $extension);

            if (! $this->disk->exists($path)) {
                Log::error("(DocumentService) [{$jobName}] - Document not found for URL generation (guid: {$guid})");

                return null;
            }

            if ($this->isS3) {
                Log::debug('[DocumentService] Generating S3 temporary URL', [
                    'document_id' => $guid,
                    'expires_at' => $expirationMinutes,
                ]);

                return $this->disk->temporaryUrl(
                    $path,
                    now()->addMinutes($expirationMinutes)
                );
            }

            Log::debug('[DocumentService] Generating local signed URL', [
                'document_id' => $guid,
                'expires_at' => $expirationMinutes,
            ]);

            return URL::temporarySignedRoute(
                'documents.serve',
                now()->addMinutes($expirationMinutes),
                ['guid' => $guid, 'type' => $type, 'extension' => $extension]
            );
        } catch (\Exception $e) {
            Log::error('[DocumentService] Secure URL generation error', [
                'error' => $e->getMessage(),
                'document_id' => $guid,
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Delete a document from storage.
     *
     * @param string $guid
     * @param string $jobName
     * @param string $type
     * @param string $extension
     * @return bool
     */
    public function deleteDocument(string $guid, string $jobName, string $type = 'receipts', string $extension = 'pdf'): bool
    {
        try {
            $path = $this->getPath($guid, $type, $extension);

            if (! $this->disk->exists($path)) {
                Log::warning("(DocumentService) [{$jobName}] - Document not found for deletion (guid: {$guid})");

                return true; // Consider it a success if file doesn't exist
            }

            $success = $this->disk->delete($path);

            if ($success) {
                Log::info("(DocumentService) [{$jobName}] - Document deleted (guid: {$guid})");
            } else {
                Log::error('[DocumentService] Document deletion failed', [
                    'error' => 'Failed to delete document',
                    'document_id' => $guid,
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('[DocumentService] Document deletion error', [
                'error' => $e->getMessage(),
                'document_id' => $guid,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Check if a document exists.
     */
    public function documentExists(string $guid, string $type = 'receipts', string $extension = 'pdf'): bool
    {
        return $this->disk->exists($this->getPath($guid, $type, $extension));
    }

    /**
     * Get the storage path for a document.
     */
    protected function getPath(string $guid, string $type, string $extension): string
    {
        return trim("{$type}/{$guid}.{$extension}", '/');
    }

}
