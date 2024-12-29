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

        // Store the original file in permanent storage
        $fileContent = file_get_contents($incomingFile->getRealPath());
        $this->storeDocument(
            $fileContent,
            $fileGUID,
            'receipts',
            $incomingFile->getClientOriginalExtension()
        );

        $fileMetaData = $this->createFileModel($incomingFile, $fileGUID);
        Cache::put("job.{$jobID}.fileMetaData", $fileMetaData, now()->addMinutes(5));

        Log::info('DocumentService - processUpload Complete', [
            'jobID' => $jobID,
            'fileGUID' => $fileGUID,
            'fileMetaData' => $fileMetaData
        ]);

        Bus::chain([
            new ProcessFile($jobID),
            new ProcessReceipt($jobID),
            new MatchMerchant($jobID),
            new DeleteWorkingFiles($jobID),
        ])->dispatch();

        return true;
    }

    /**
     * Store a document in the storage system
     */
    public function storeDocument(string $content, string $guid, string $type = 'receipts', string $extension = 'pdf'): bool
    {
        try {
            $path = $this->getPath($guid, $type, $extension);
            $success = $this->disk->put($path, $content);
            
            if (!$success) {
                Log::error("Failed to store document", [
                    'guid' => $guid,
                    'type' => $type,
                    'extension' => $extension,
                    'disk' => config('filesystems.default')
                ]);
                return false;
            }

            Log::info("Document stored successfully", [
                'guid' => $guid,
                'path' => $path,
                'type' => $type,
                'disk' => config('filesystems.disks.documents.driver')
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Error storing document: " . $e->getMessage(), [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension
            ]);
            return false;
        }
    }

    /**
     * Get a document's content directly
     */
    public function getDocument(string $guid, string $type = 'receipts', string $extension = 'pdf')
    {
        try {
            $path = $this->getPath($guid, $type, $extension);
            
            if (!$this->disk->exists($path)) {
                Log::error("Document not found", [
                    'guid' => $guid,
                    'path' => $path,
                    'disk' => config('filesystems.disks.documents.driver')
                ]);
                return null;
            }

            return $this->disk->get($path);
        } catch (\Exception $e) {
            Log::error("Error retrieving document: " . $e->getMessage(), [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension
            ]);
            return null;
        }
    }

    /**
     * Get a secure URL for accessing the document
     */
    public function getSecureUrl(string $guid, string $type = 'receipts', string $extension = 'pdf', int $expirationMinutes = 5): ?string
    {
        try {
            $path = $this->getPath($guid, $type, $extension);
            
            if (!$this->disk->exists($path)) {
                Log::error("Document not found for URL generation", [
                    'guid' => $guid,
                    'path' => $path
                ]);
                return null;
            }

            if ($this->isS3) {
                Log::info("Generating S3 temporary URL", [
                    'guid' => $guid,
                    'path' => $path,
                    'expiration' => $expirationMinutes
                ]);
                return $this->disk->temporaryUrl(
                    $path,
                    now()->addMinutes($expirationMinutes)
                );
            }

            Log::info("Generating local signed URL", [
                'guid' => $guid,
                'path' => $path,
                'expiration' => $expirationMinutes
            ]);
            
            return URL::temporarySignedRoute(
                'documents.serve',
                now()->addMinutes($expirationMinutes),
                ['guid' => $guid, 'type' => $type, 'extension' => $extension]
            );
        } catch (\Exception $e) {
            Log::error("Error generating secure URL: " . $e->getMessage(), [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension,
                'driver' => config('filesystems.disks.documents.driver')
            ]);
            return null;
        }
    }

    /**
     * Delete a document from storage
     */
    public function deleteDocument(string $guid, string $type = 'receipts', string $extension = 'pdf'): bool
    {
        try {
            $path = $this->getPath($guid, $type, $extension);
            
            if (!$this->disk->exists($path)) {
                Log::warning("Document not found for deletion", [
                    'guid' => $guid,
                    'path' => $path
                ]);
                return true; // Consider it a success if file doesn't exist
            }

            $success = $this->disk->delete($path);
            
            if ($success) {
                Log::info("Document deleted successfully", [
                    'guid' => $guid,
                    'path' => $path
                ]);
            } else {
                Log::error("Failed to delete document", [
                    'guid' => $guid,
                    'path' => $path
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error("Error deleting document: " . $e->getMessage(), [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension
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
            Log::info('DocumentService - storeWorkingFile Complete', [
                'fileName' => $fileName,
                'path' => $storedFile
            ]);
            return Storage::disk('local')->path($storedFile);
        } catch (\Exception $e) {
            Log::error('Error storing working file: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a file model in the database
     */
    private function createFileModel($incomingFile, $fileGUID): array
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

        Log::info('DocumentService created file in DB', $fileModel->toArray());

        return [
            'fileID' => $fileModel->id,
            'fileGUID' => $fileGUID,
            'filePath' => $filePath,
            'fileExtension' => $fileExtension,
        ];
    }
} 