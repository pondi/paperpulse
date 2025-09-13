<?php

namespace App\Services;

use App\Contracts\Services\FileMetadataContract;
use App\Contracts\Services\FileStorageContract;
use App\Contracts\Services\FileValidationContract;
use App\Jobs\Files\ProcessFile;
use App\Models\File;
use App\Services\Files\FileJobChainDispatcher;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates end-to-end file ingestion for receipts and documents.
 *
 * Responsibilities:
 * - Validate incoming file payloads and types
 * - Persist working/original files (local and S3)
 * - Create DB records and cache job metadata
 * - Dispatch the appropriate queued job chain per file type
 */
class FileProcessingService
{
    protected FileStorageContract $fileStorage;

    protected FileMetadataContract $fileMetadata;

    protected FileValidationContract $fileValidation;

    protected TextExtractionService $textExtractionService;

    protected FileJobChainDispatcher $jobChainDispatcher;

    public function __construct(
        FileStorageContract $fileStorage,
        FileMetadataContract $fileMetadata,
        FileValidationContract $fileValidation,
        TextExtractionService $textExtractionService,
        FileJobChainDispatcher $jobChainDispatcher
    ) {
        $this->fileStorage = $fileStorage;
        $this->fileMetadata = $fileMetadata;
        $this->fileValidation = $fileValidation;
        $this->textExtractionService = $textExtractionService;
        $this->jobChainDispatcher = $jobChainDispatcher;
    }

    /**
     * Process a file from any source (upload, PulseDav, etc.).
     * This is the unified entrypoint all file processing should use.
     *
     * @param  array  $fileData  Canonical file data (content, fileName, extension, size, etc.)
     * @param  string  $fileType  Either 'receipt' or 'document'
     * @param  int  $userId  Owner of the file
     * @param  array  $metadata  Optional metadata (e.g. jobId, jobName, source, tagIds)
     * @return array{success:bool,fileId:int,fileGuid:string,jobId:string,jobName:string}
     *
     * @throws Exception
     */
    public function processFile(array $fileData, string $fileType, int $userId, array $metadata = []): array
    {
        try {
            // Generate unique identifiers
            $jobId = $metadata['jobId'] ?? (string) Str::uuid();
            $fileGuid = $this->fileStorage->generateFileGuid();
            $jobName = $metadata['jobName'] ?? $this->fileMetadata->generateJobName();

            Log::info("[FileProcessing] [{$jobName}] Processing file", [
                'file_name' => $fileData['fileName'],
                'file_type' => $fileType,
                'user_id' => $userId,
                'source' => $fileData['source'] ?? 'unknown',
            ]);

            // Validate file data
            $validation = $this->fileValidation->validateFileData($fileData, $fileType);
            if (! $validation['valid']) {
                throw new Exception('File validation failed: '.implode(', ', $validation['errors']));
            }

            // Store working file locally
            $workingPath = $this->fileStorage->storeWorkingContent(
                $fileData['content'],
                $fileGuid,
                $fileData['extension']
            );

            // Create file record in database
            $file = $this->fileMetadata->createFileRecordFromData($fileData, $fileGuid, $fileType, $userId);

            // Store original file to S3 storage bucket
            $s3Path = $this->fileStorage->storeToS3(
                $fileData['content'],
                $userId,
                $fileGuid,
                $fileType,
                'original',
                $fileData['extension']
            );

            // Update file record with S3 path
            $this->fileMetadata->updateFileWithS3Path($file, $s3Path);

            // Prepare metadata for job chain
            $fileMetadata = $this->fileMetadata->prepareFileMetadata(
                $file,
                $fileGuid,
                $fileData,
                $workingPath,
                $s3Path,
                $jobName,
                $metadata
            );

            // Cache metadata for job chain
            Cache::put("job.{$jobId}.fileMetaData", $fileMetadata, now()->addHours(2));

            // Dispatch appropriate job chain based on file type
            $this->jobChainDispatcher->dispatch($jobId, $fileType);

            Log::info("[FileProcessing] [{$jobName}] File processing initiated", [
                'job_id' => $jobId,
                'file_id' => $file->id,
                'file_guid' => $fileGuid,
            ]);

            return [
                'success' => true,
                'fileId' => $file->id,
                'fileGuid' => $fileGuid,
                'jobId' => $jobId,
                'jobName' => $jobName,
            ];
        } catch (Exception $e) {
            Log::error('[FileProcessing] File processing failed', [
                'error' => $e->getMessage(),
                'file_name' => $fileData['fileName'] ?? 'unknown',
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Process an uploaded file (receipt or document).
     * Delegates to the unified processFile method.
     *
     * @param  string  $fileType  Either 'receipt' or 'document'
     * @return array{success:bool,fileId:int,fileGuid:string,jobId:string,jobName:string}
     *
     * @throws Exception
     */
    public function processUpload(UploadedFile $uploadedFile, string $fileType, int $userId, array $metadata = []): array
    {
        try {
            // Validate uploaded file
            $validation = $this->fileValidation->validateUploadedFile($uploadedFile, $fileType);
            if (! $validation['valid']) {
                throw new Exception('File validation failed: '.implode(', ', $validation['errors']));
            }

            // Extract file data
            $fileData = $this->fileMetadata->extractFileDataFromUpload($uploadedFile, 'upload');

            // Use the unified processFile method
            return $this->processFile($fileData, $fileType, $userId, $metadata);

        } catch (Exception $e) {
            Log::error('[FileProcessing] Upload processing failed', [
                'error' => $e->getMessage(),
                'file_name' => $uploadedFile->getClientOriginalName(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Process a PulseDav file from the incoming bucket.
     * Delegates to the unified processFile method.
     *
     * @param  string  $incomingPath  Path within the 'pulsedav' disk
     * @param  string  $fileType  Either 'receipt' or 'document'
     * @param  array  $metadata  Additional metadata to carry through the chain
     * @return array{success:bool,fileId:int,fileGuid:string,jobId:string,jobName:string}
     *
     * @throws Exception
     */
    public function processPulseDavFile(string $incomingPath, string $fileType, int $userId, array $metadata = []): array
    {
        try {
            Log::info('[FileProcessing] Processing PulseDav file', [
                'incoming_path' => $incomingPath,
                'file_type' => $fileType,
                'user_id' => $userId,
                'metadata' => $metadata,
            ]);

            // Check if file exists in PulseDav bucket
            if (! $this->fileStorage->existsInS3('pulsedav', $incomingPath)) {
                Log::error('[FileProcessing] File not found in PulseDav bucket', [
                    'incoming_path' => $incomingPath,
                    'disk' => 'pulsedav',
                ]);
                throw new \Exception("File not found in PulseDav bucket: {$incomingPath}");
            }

            // Download file content from incoming bucket
            $fileContent = $this->fileStorage->getFromS3('pulsedav', $incomingPath);
            $fileSize = $this->fileStorage->getSizeFromS3('pulsedav', $incomingPath);

            // Extract file data
            $fileData = $this->fileMetadata->extractFileDataFromPulseDav($incomingPath, $fileContent, $fileSize);

            // Add PulseDav-specific metadata
            $metadata['source'] = 'pulsedav';
            $metadata['incomingPath'] = $incomingPath;

            // Use the unified processFile method
            $result = $this->processFile($fileData, $fileType, $userId, $metadata);

            // Delete file from incoming bucket after successful processing
            if ($result['success']) {
                try {
                    $this->fileStorage->deleteFromS3('pulsedav', $incomingPath);
                    Log::info('[FileProcessing] Deleted file from incoming bucket', [
                        'incoming_path' => $incomingPath,
                    ]);
                } catch (Exception $e) {
                    Log::warning('[FileProcessing] Failed to delete file from incoming bucket', [
                        'incoming_path' => $incomingPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $result;
        } catch (Exception $e) {
            Log::error('[FileProcessing] PulseDav processing failed', [
                'error' => $e->getMessage(),
                'incoming_path' => $incomingPath,
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Validate file type is supported for the given extension.
     *
     * @param  string  $fileType  Either 'receipt' or 'document'
     */
    public function isSupported(string $extension, string $fileType): bool
    {
        return $this->fileValidation->isSupported($extension, $fileType);
    }

    /**
     * Get maximum file size for a file type, in bytes.
     *
     * @param  string  $fileType  Either 'receipt' or 'document'
     */
    public function getMaxFileSize(string $fileType): int
    {
        return $this->fileValidation->getMaxFileSize($fileType);
    }
}
