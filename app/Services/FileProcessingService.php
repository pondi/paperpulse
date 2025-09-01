<?php

namespace App\Services;

use App\Contracts\Services\FileMetadataContract;
use App\Contracts\Services\FileStorageContract;
use App\Contracts\Services\FileValidationContract;
use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Documents\ProcessDocument;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Jobs\PulseDav\UpdatePulseDavFileStatus;
use App\Jobs\Receipts\MatchMerchant;
use App\Jobs\Receipts\ProcessReceipt;
use App\Jobs\System\ApplyTags;
use App\Models\File;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileProcessingService
{
    protected FileStorageContract $fileStorage;

    protected FileMetadataContract $fileMetadata;

    protected FileValidationContract $fileValidation;

    protected TextExtractionService $textExtractionService;

    public function __construct(
        FileStorageContract $fileStorage,
        FileMetadataContract $fileMetadata,
        FileValidationContract $fileValidation,
        TextExtractionService $textExtractionService
    ) {
        $this->fileStorage = $fileStorage;
        $this->fileMetadata = $fileMetadata;
        $this->fileValidation = $fileValidation;
        $this->textExtractionService = $textExtractionService;
    }

    /**
     * Process a file from any source (upload, PulseDav, etc.)
     * This is the unified method that all file processing should use
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
            $this->dispatchJobChain($jobId, $fileType);

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
     * Process an uploaded file (receipt or document)
     * This method now delegates to the unified processFile method
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
     * Process a PulseDav file
     * This method now delegates to the unified processFile method
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
     * Dispatch the appropriate job chain based on file type
     */
    protected function dispatchJobChain(string $jobId, string $fileType): void
    {
        // Get metadata
        $metadata = Cache::get("job.{$jobId}.fileMetaData");
        $source = $metadata['metadata']['source'] ?? 'upload';
        $tagIds = $metadata['metadata']['tagIds'] ?? [];
        $pulseDavFileId = $metadata['metadata']['pulseDavFileId'] ?? null;

        Log::info('Dispatching job chain', [
            'jobId' => $jobId,
            'fileType' => $fileType,
            'source' => $source,
            'tagIds' => $tagIds,
            'pulseDavFileId' => $pulseDavFileId,
            'jobName' => $metadata['jobName'] ?? 'Unknown',
        ]);

        // Determine queue based on file type
        $queue = $fileType === 'receipt' ? 'receipts' : 'documents';

        // Base jobs for processing
        $jobs = [];

        if ($fileType === 'receipt') {
            $jobs = [
                (new ProcessFile($jobId))->onQueue($queue),
                (new ProcessReceipt($jobId))->onQueue($queue),
                (new MatchMerchant($jobId))->onQueue($queue),
            ];
        } else {
            $jobs = [
                (new ProcessFile($jobId))->onQueue($queue),
                (new ProcessDocument($jobId))->onQueue($queue),
                (new AnalyzeDocument($jobId))->onQueue($queue),
            ];
        }

        // Add tag application if there are tags
        if (! empty($tagIds) && isset($metadata['fileId'])) {
            $file = \App\Models\File::find($metadata['fileId']);
            if ($file) {
                $jobs[] = (new ApplyTags($jobId, $file, $tagIds))->onQueue($queue);
            }
        }

        // Always add cleanup job
        $jobs[] = (new DeleteWorkingFiles($jobId))->onQueue($queue);

        // Add PulseDav status update if this is from PulseDav
        if ($source === 'pulsedav' && $pulseDavFileId && isset($metadata['fileId'])) {
            $file = \App\Models\File::find($metadata['fileId']);
            if ($file) {
                $jobs[] = (new UpdatePulseDavFileStatus($jobId, $file, $pulseDavFileId, $fileType))->onQueue($queue);
            }
        }

        Bus::chain($jobs)->dispatch();
    }

    /**
     * Validate file type is supported
     */
    public function isSupported(string $extension, string $fileType): bool
    {
        return $this->fileValidation->isSupported($extension, $fileType);
    }

    /**
     * Get maximum file size for a file type
     */
    public function getMaxFileSize(string $fileType): int
    {
        return $this->fileValidation->getMaxFileSize($fileType);
    }
}
