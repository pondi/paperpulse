<?php

namespace App\Services;

use App\Jobs\AnalyzeDocument;
use App\Jobs\ApplyTags;
use App\Jobs\DeleteWorkingFiles;
use App\Jobs\MatchMerchant;
use App\Jobs\ProcessDocument;
use App\Jobs\ProcessFile;
use App\Jobs\ProcessReceipt;
use App\Jobs\UpdatePulseDavFileStatus;
use App\Models\File;
use App\Services\S3StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class FileProcessingService
{
    protected StorageService $storageService;
    protected TextExtractionService $textExtractionService;
    
    public function __construct(
        StorageService $storageService,
        TextExtractionService $textExtractionService
    ) {
        $this->storageService = $storageService;
        $this->textExtractionService = $textExtractionService;
    }
    
    /**
     * Process a file from any source (upload, PulseDav, etc.)
     * This is the unified method that all file processing should use
     * 
     * @param array $fileData Array containing file information
     * @param string $fileType 'receipt' or 'document'
     * @param int $userId User ID
     * @param array $metadata Additional metadata
     * @return array File processing information
     */
    public function processFile(array $fileData, string $fileType, int $userId, array $metadata = []): array
    {
        try {
            // Generate unique identifiers
            $jobId = $metadata['jobId'] ?? (string) Str::uuid();
            $fileGuid = (string) Str::uuid();
            $jobName = $metadata['jobName'] ?? $this->generateJobName();
            
            Log::info("[FileProcessingService] [{$jobName}] Processing file", [
                'file_name' => $fileData['fileName'],
                'file_type' => $fileType,
                'user_id' => $userId,
                'source' => $fileData['source'] ?? 'unknown',
            ]);
            
            // Store working file locally
            $workingPath = $this->storeWorkingContent(
                $fileData['content'], 
                $fileGuid, 
                $fileData['extension']
            );
            
            // Create file record in database
            $file = $this->createFileRecordFromData($fileData, $fileGuid, $fileType, $userId);
            
            // Store original file to S3 storage bucket
            $s3Path = $this->storageService->storeFile(
                $fileData['content'],
                $userId,
                $fileGuid,
                $fileType,
                'original',
                $fileData['extension']
            );
            
            // Update file record with S3 path
            $file->s3_original_path = $s3Path;
            $file->save();
            
            // Prepare metadata for job chain
            $fileMetadata = [
                'fileId' => $file->id,
                'fileGuid' => $fileGuid,
                'filePath' => $workingPath,
                'fileExtension' => $fileData['extension'],
                'fileType' => $fileType,
                'userId' => $userId,
                's3OriginalPath' => $s3Path,
                'jobName' => $jobName,
                'metadata' => $metadata,
            ];
            
            // Cache metadata for job chain
            Cache::put("job.{$jobId}.fileMetaData", $fileMetadata, now()->addHours(2));
            
            // Dispatch appropriate job chain based on file type
            $this->dispatchJobChain($jobId, $fileType);
            
            Log::info("[FileProcessingService] [{$jobName}] File processing initiated", [
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
            Log::error('[FileProcessingService] File processing failed', [
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
     * 
     * @param UploadedFile $uploadedFile The uploaded file
     * @param string $fileType 'receipt' or 'document'
     * @param int $userId User ID
     * @param array $metadata Additional metadata
     * @return array File processing information
     */
    public function processUpload(UploadedFile $uploadedFile, string $fileType, int $userId, array $metadata = []): array
    {
        try {
            // Read file content
            $content = file_get_contents($uploadedFile->getRealPath());
            
            // Prepare file data for unified processing
            $fileData = [
                'fileName' => $uploadedFile->getClientOriginalName(),
                'extension' => $uploadedFile->getClientOriginalExtension(),
                'mimeType' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'content' => $content,
                'source' => 'upload',
            ];
            
            // Use the unified processFile method
            return $this->processFile($fileData, $fileType, $userId, $metadata);
            
        } catch (Exception $e) {
            Log::error('[FileProcessingService] Upload processing failed', [
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
     * 
     * @param string $incomingPath Path in incoming S3 bucket
     * @param string $fileType 'receipt' or 'document'
     * @param int $userId User ID
     * @param array $metadata Additional metadata
     * @return array File processing information
     */
    public function processPulseDavFile(string $incomingPath, string $fileType, int $userId, array $metadata = []): array
    {
        try {
            $filename = basename($incomingPath);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            
            Log::info("[FileProcessingService] Processing PulseDav file", [
                'incoming_path' => $incomingPath,
                'file_type' => $fileType,
                'user_id' => $userId,
                'metadata' => $metadata,
            ]);
            
            // Check if file exists in PulseDav bucket
            if (!S3StorageService::exists('pulsedav', $incomingPath)) {
                Log::error('[FileProcessingService] File not found in PulseDav bucket', [
                    'incoming_path' => $incomingPath,
                    'disk' => 'pulsedav',
                ]);
                throw new \Exception("File not found in PulseDav bucket: {$incomingPath}");
            }
            
            // Download file content from incoming bucket using our wrapper
            $fileContent = S3StorageService::get('pulsedav', $incomingPath);
            $fileSize = S3StorageService::size('pulsedav', $incomingPath);
            
            // Prepare file data for unified processing
            $fileData = [
                'fileName' => $filename,
                'extension' => $extension,
                'mimeType' => null, // Will be determined later
                'size' => $fileSize,
                'content' => $fileContent,
                'source' => 'pulsedav',
                'incomingPath' => $incomingPath, // Store for deletion after processing
            ];
            
            // Add PulseDav-specific metadata
            $metadata['source'] = 'pulsedav';
            $metadata['incomingPath'] = $incomingPath;
            
            // Use the unified processFile method
            $result = $this->processFile($fileData, $fileType, $userId, $metadata);
            
            // Delete file from incoming bucket after successful processing
            if ($result['success']) {
                try {
                    S3StorageService::delete('pulsedav', $incomingPath);
                    Log::info('[FileProcessingService] Deleted file from incoming bucket', [
                        'incoming_path' => $incomingPath,
                    ]);
                } catch (Exception $e) {
                    Log::warning('[FileProcessingService] Failed to delete file from incoming bucket', [
                        'incoming_path' => $incomingPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            return $result;
        } catch (Exception $e) {
            Log::error('[FileProcessingService] PulseDav processing failed', [
                'error' => $e->getMessage(),
                'incoming_path' => $incomingPath,
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }
    
    /**
     * Dispatch the appropriate job chain based on file type
     * 
     * @param string $jobId Job ID
     * @param string $fileType 'receipt' or 'document'
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
        if (!empty($tagIds) && isset($metadata['fileId'])) {
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
     * Store uploaded file locally for processing
     * 
     * @param UploadedFile $uploadedFile
     * @param string $fileGuid
     * @return string Local file path
     */
    protected function storeWorkingFile(UploadedFile $uploadedFile, string $fileGuid): string
    {
        try {
            $fileName = $fileGuid . '.' . $uploadedFile->getClientOriginalExtension();
            $storedFile = $uploadedFile->storeAs('uploads', $fileName, 'local');
            
            Log::debug('[FileProcessingService] Working file stored', [
                'file_path' => $storedFile,
                'file_guid' => $fileGuid,
            ]);
            
            return Storage::disk('local')->path($storedFile);
        } catch (Exception $e) {
            Log::error('[FileProcessingService] Working file storage failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
            ]);
            throw $e;
        }
    }
    
    /**
     * Store file content locally for processing
     * 
     * @param string $content File content
     * @param string $fileGuid
     * @param string $extension
     * @return string Local file path
     */
    protected function storeWorkingContent(string $content, string $fileGuid, string $extension): string
    {
        try {
            $fileName = $fileGuid . '.' . $extension;
            $path = 'uploads/' . $fileName;
            
            Storage::disk('local')->put($path, $content);
            
            Log::debug('[FileProcessingService] Working content stored', [
                'file_path' => $path,
                'file_guid' => $fileGuid,
            ]);
            
            return Storage::disk('local')->path($path);
        } catch (Exception $e) {
            Log::error('[FileProcessingService] Working content storage failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
            ]);
            throw $e;
        }
    }
    
    /**
     * Create file record in database from file data
     * 
     * @param array $fileData
     * @param string $fileGuid
     * @param string $fileType
     * @param int $userId
     * @return File
     */
    protected function createFileRecordFromData(array $fileData, string $fileGuid, string $fileType, int $userId): File
    {
        $file = new File();
        $file->user_id = $userId;
        $file->fileName = $fileData['fileName'];
        $file->fileExtension = $fileData['extension'];
        $file->fileType = $fileData['mimeType'] ?? $this->getMimeType($fileData['extension']);
        $file->fileSize = $fileData['size'];
        $file->guid = $fileGuid;
        $file->file_type = $fileType;
        $file->processing_type = $fileType;
        $file->uploaded_at = now();
        $file->save();
        
        Log::debug('[FileProcessingService] File record created', [
            'file_id' => $file->id,
            'file_guid' => $fileGuid,
            'file_type' => $fileType,
            'source' => $fileData['source'] ?? 'unknown',
        ]);
        
        return $file;
    }
    
    /**
     * Create file record in database (legacy method for UploadedFile)
     * 
     * @param UploadedFile $uploadedFile
     * @param string $fileGuid
     * @param string $fileType
     * @param int $userId
     * @return File
     */
    protected function createFileRecord(UploadedFile $uploadedFile, string $fileGuid, string $fileType, int $userId): File
    {
        $fileData = [
            'fileName' => $uploadedFile->getClientOriginalName(),
            'extension' => $uploadedFile->getClientOriginalExtension(),
            'mimeType' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
        ];
        
        return $this->createFileRecordFromData($fileData, $fileGuid, $fileType, $userId);
    }
    
    /**
     * Generate a friendly job name
     * 
     * @return string
     */
    protected function generateJobName(): string
    {
        $adjectives = ['swift', 'bright', 'stellar', 'cosmic', 'quantum', 'digital', 'cyber', 'turbo', 'mega', 'ultra'];
        $nouns = ['pulse', 'wave', 'stream', 'flow', 'burst', 'beam', 'spark', 'flash', 'surge', 'blast'];
        
        $adjective = $adjectives[array_rand($adjectives)];
        $noun = $nouns[array_rand($nouns)];
        
        return "{$adjective}-{$noun}-" . substr(md5(microtime()), rand(0, 26), 5);
    }
    
    /**
     * Validate file type is supported
     * 
     * @param string $extension File extension
     * @param string $fileType 'receipt' or 'document'
     * @return bool
     */
    public function isSupported(string $extension, string $fileType): bool
    {
        $extension = strtolower($extension);
        
        if ($fileType === 'receipt') {
            $supported = explode(',', config('documents.supported_receipt_formats'));
        } else {
            $supported = explode(',', config('documents.supported_document_formats'));
        }
        
        return in_array($extension, $supported);
    }
    
    /**
     * Get maximum file size for a file type
     * 
     * @param string $fileType 'receipt' or 'document'
     * @return int Size in bytes
     */
    public function getMaxFileSize(string $fileType): int
    {
        if ($fileType === 'receipt') {
            return config('documents.max_receipt_size') * 1024 * 1024; // MB to bytes
        } else {
            return config('documents.max_document_size') * 1024 * 1024; // MB to bytes
        }
    }
    
    /**
     * Get MIME type from file extension
     * 
     * @param string $extension File extension
     * @return string MIME type
     */
    protected function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
        ];
        
        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }
}