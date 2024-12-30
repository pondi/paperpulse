<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Bus;
use App\Models\File;
use App\Jobs\ProcessReceipt;
use App\Jobs\MatchMerchant;
use App\Jobs\ProcessFile;
use App\Jobs\DeleteWorkingFiles;

class DocumentService
{
    protected $disk;
    protected $isS3;
    
    public function __construct()
    {
        $this->disk = Storage::disk('documents');
        $this->isS3 = config('filesystems.disks.documents.driver') === 's3';
    }

    /**
     * Process an uploaded file
     */
    public function processUpload($incomingFile, $fileType = 'receipt')
    {
        $jobID = (string) Str::uuid();
        $fileGUID = (string) Str::uuid();
        $jobName = $this->generateJobName();

        // Store the original file in permanent storage
        $fileContent = file_get_contents($incomingFile->getRealPath());
        $this->storeDocument(
            $fileContent,
            $fileGUID,
            $jobName,
            'receipts',
            $incomingFile->getClientOriginalExtension()
        );

        $fileMetaData = $this->createFileModel($incomingFile, $fileGUID, $jobName);
        $fileMetaData['jobName'] = $jobName;
        Cache::put("job.{$jobID}.fileMetaData", $fileMetaData, now()->addMinutes(5));

        Log::info("(DocumentService) [{$jobName}] - Upload processing started (file: {$incomingFile->getClientOriginalName()})");

        Bus::chain([
            new ProcessFile($jobID),
            new ProcessReceipt($jobID),
            new MatchMerchant($jobID),
            new DeleteWorkingFiles($jobID),
        ])->dispatch();

        Log::debug("(DocumentService) [{$jobName}] Upload processing details", [
            'file_name' => $incomingFile->getClientOriginalName(),
            'size' => $incomingFile->getSize(),
            'mime_type' => $incomingFile->getMimeType()
        ]);

        return true;
    }

    /**
     * Store a document in the storage system
     */
    public function storeDocument(string $content, string $guid, string $jobName, string $type = 'receipts', string $extension = 'pdf'): bool
    {
        try {
            $path = $this->getPath($guid, $type, $extension);
            $success = $this->disk->put($path, $content);
            
            if (!$success) {
                Log::error('[DocumentService] Document storage failed', [
                    'error' => "Failed to store document",
                    'file_name' => $incomingFile->getClientOriginalName(),
                    'trace' => $e->getTraceAsString()
                ]);
                return false;
            }

            Log::info("(DocumentService) [{$jobName}] - Document stored (guid: {$guid})");

            return true;
        } catch (\Exception $e) {
            Log::error('[DocumentService] Document storage error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get a document's content directly
     */
    public function getDocument(string $guid, string $jobName, string $type = 'receipts', string $extension = 'pdf')
    {
        try {
            $path = $this->getPath($guid, $type, $extension);
            
            if (!$this->disk->exists($path)) {
                Log::error("(DocumentService) [{$jobName}] - Document not found for retrieval (guid: {$guid})");
                return null;
            }

            return $this->disk->get($path);
        } catch (\Exception $e) {
            Log::error('[DocumentService] Document retrieval error', [
                'error' => $e->getMessage(),
                'document_id' => $guid,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get a secure URL for accessing the document
     */
    public function getSecureUrl(string $guid, string $jobName, string $type = 'receipts', string $extension = 'pdf', int $expirationMinutes = 5): ?string
    {
        try {
            $path = $this->getPath($guid, $type, $extension);
            
            if (!$this->disk->exists($path)) {
                Log::error("(DocumentService) [{$jobName}] - Document not found for URL generation (guid: {$guid})");
                return null;
            }

            if ($this->isS3) {
                Log::debug('[DocumentService] Generating S3 temporary URL', [
                    'document_id' => $guid,
                    'expires_at' => $expirationMinutes
                ]);
                return $this->disk->temporaryUrl(
                    $path,
                    now()->addMinutes($expirationMinutes)
                );
            }

            Log::debug('[DocumentService] Generating local signed URL', [
                'document_id' => $guid,
                'expires_at' => $expirationMinutes
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
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Delete a document from storage
     */
    public function deleteDocument(string $guid, string $jobName, string $type = 'receipts', string $extension = 'pdf'): bool
    {
        try {
            $path = $this->getPath($guid, $type, $extension);
            
            if (!$this->disk->exists($path)) {
                Log::warning("(DocumentService) [{$jobName}] - Document not found for deletion (guid: {$guid})");
                return true; // Consider it a success if file doesn't exist
            }

            $success = $this->disk->delete($path);
            
            if ($success) {
                Log::info("(DocumentService) [{$jobName}] - Document deleted (guid: {$guid})");
            } else {
                Log::error('[DocumentService] Document deletion failed', [
                    'error' => "Failed to delete document",
                    'document_id' => $guid
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('[DocumentService] Document deletion error', [
                'error' => $e->getMessage(),
                'document_id' => $guid,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Check if a document exists
     */
    public function documentExists(string $guid, string $type = 'receipts', string $extension = 'pdf'): bool
    {
        return $this->disk->exists($this->getPath($guid, $type, $extension));
    }

    /**
     * Get the storage path for a document
     */
    protected function getPath(string $guid, string $type, string $extension): string
    {
        return trim("{$type}/{$guid}.{$extension}", '/');
    }

    /**
     * Store a working file for processing
     */
    private function storeWorkingFile($incomingFile, string $fileGUID): string
    {
        try {
            $fileName = $fileGUID . '.' . $incomingFile->getClientOriginalExtension();
            $storedFile = $incomingFile->storeAs('uploads', $fileName, 'local');
            Log::debug('[DocumentService] Working file stored', [
                'file_path' => $storedFile,
                'file_guid' => $fileGUID
            ]);
            return Storage::disk('local')->path($storedFile);
        } catch (\Exception $e) {
            Log::error('[DocumentService] Working file storage error', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGUID,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Create a file model in the database
     */
    private function createFileModel($incomingFile, $fileGUID, $jobName): array
    {
        $filePath = $this->storeWorkingFile($incomingFile, $fileGUID);
        $fileExtension = $incomingFile->getClientOriginalExtension();
        $fileName = $incomingFile->getClientOriginalName();
        $fileType = $incomingFile->getClientMimeType();
        $fileSize = $incomingFile->getSize();

        $fileModel = new File;
        $fileModel->fileName = $fileName;
        $fileModel->fileExtension = $fileExtension;
        $fileModel->fileType = $fileType;
        $fileModel->fileSize = $fileSize;
        $fileModel->guid = $fileGUID;
        $fileModel->uploaded_at = now();
        $fileModel->save();

        Log::debug("(DocumentService) [{$jobName}] - File record details", $fileModel->toArray());

        return [
            'fileID' => $fileModel->id,
            'fileGUID' => $fileGUID,
            'filePath' => $filePath,
            'fileExtension' => $fileExtension,
        ];
    }

    private function generateJobName(): string
    {
        $adjectives = ['purple', 'blue', 'green', 'yellow', 'red', 'awesome', 'shiny', 'glorious', 'mighty', 'cosmic'];
        $nouns = ['vortex', 'planet', 'star', 'pulsar', 'quasar', 'blackhole', 'wormhole', 'asteroid', 'comet', 'galaxy'];

        $adjective = $adjectives[array_rand($adjectives)];
        $noun = $nouns[array_rand($nouns)];

        return "{$adjective}-{$noun}-" . substr(md5(microtime()),rand(0,26),5);
    }
} 