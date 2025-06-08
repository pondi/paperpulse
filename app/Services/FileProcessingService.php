<?php

namespace App\Services;

use App\Jobs\DeleteWorkingFiles;
use App\Jobs\MatchMerchant;
use App\Jobs\ProcessDocument;
use App\Jobs\ProcessFile;
use App\Jobs\ProcessReceipt;
use App\Models\File;
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
     * Process an uploaded file (receipt or document)
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
            // Generate unique identifiers
            $jobId = (string) Str::uuid();
            $fileGuid = (string) Str::uuid();
            $jobName = $this->generateJobName();
            
            Log::info("[FileProcessingService] [{$jobName}] Processing upload", [
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_type' => $fileType,
                'user_id' => $userId,
            ]);
            
            // Store working file locally
            $workingPath = $this->storeWorkingFile($uploadedFile, $fileGuid);
            
            // Create file record in database
            $file = $this->createFileRecord($uploadedFile, $fileGuid, $fileType, $userId);
            
            // Store original file to S3 storage bucket
            $fileContent = file_get_contents($workingPath);
            $s3Path = $this->storageService->storeFile(
                $fileContent,
                $userId,
                $fileGuid,
                $fileType,
                'original',
                $uploadedFile->getClientOriginalExtension()
            );
            
            // Update file record with S3 path
            $file->s3_original_path = $s3Path;
            $file->save();
            
            // Prepare metadata for job chain
            $fileMetadata = [
                'fileId' => $file->id,
                'fileGuid' => $fileGuid,
                'filePath' => $workingPath,
                'fileExtension' => $uploadedFile->getClientOriginalExtension(),
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
            
            Log::info("[FileProcessingService] [{$jobName}] Upload processing initiated", [
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
            // Generate unique identifiers
            $jobId = (string) Str::uuid();
            $fileGuid = (string) Str::uuid();
            $jobName = $this->generateJobName();
            
            $filename = basename($incomingPath);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            
            Log::info("[FileProcessingService] [{$jobName}] Processing PulseDav file", [
                'incoming_path' => $incomingPath,
                'file_type' => $fileType,
                'user_id' => $userId,
            ]);
            
            // Move file from incoming to storage bucket
            $s3Path = $this->storageService->moveToStorage(
                $incomingPath,
                $userId,
                $fileGuid,
                $fileType,
                $extension
            );
            
            // Download file for local processing
            $fileContent = $this->storageService->getFile($s3Path);
            $workingPath = $this->storeWorkingContent($fileContent, $fileGuid, $extension);
            
            // Create file record
            $file = new File();
            $file->user_id = $userId;
            $file->fileName = $filename;
            $file->fileExtension = $extension;
            $file->fileType = mime_content_type($workingPath);
            $file->fileSize = strlen($fileContent);
            $file->guid = $fileGuid;
            $file->file_type = $fileType;
            $file->s3_original_path = $s3Path;
            $file->processing_type = $fileType;
            $file->uploaded_at = now();
            $file->save();
            
            // Prepare metadata for job chain
            $fileMetadata = [
                'fileId' => $file->id,
                'fileGuid' => $fileGuid,
                'filePath' => $workingPath,
                'fileExtension' => $extension,
                'fileType' => $fileType,
                'userId' => $userId,
                's3OriginalPath' => $s3Path,
                'jobName' => $jobName,
                'metadata' => $metadata,
                'source' => 'pulsedav',
            ];
            
            // Cache metadata for job chain
            Cache::put("job.{$jobId}.fileMetaData", $fileMetadata, now()->addHours(2));
            
            // Dispatch appropriate job chain
            $this->dispatchJobChain($jobId, $fileType);
            
            Log::info("[FileProcessingService] [{$jobName}] PulseDav processing initiated", [
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
        if ($fileType === 'receipt') {
            Bus::chain([
                (new ProcessFile($jobId))->onQueue('receipts'),
                (new ProcessReceipt($jobId))->onQueue('receipts'),
                (new MatchMerchant($jobId))->onQueue('receipts'),
                (new DeleteWorkingFiles($jobId))->onQueue('receipts'),
            ])->dispatch();
        } else {
            Bus::chain([
                (new ProcessFile($jobId))->onQueue('documents'),
                (new ProcessDocument($jobId))->onQueue('documents'),
                // AnalyzeDocument job will be added in Step 3
                (new DeleteWorkingFiles($jobId))->onQueue('documents'),
            ])->dispatch();
        }
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
     * Create file record in database
     * 
     * @param UploadedFile $uploadedFile
     * @param string $fileGuid
     * @param string $fileType
     * @param int $userId
     * @return File
     */
    protected function createFileRecord(UploadedFile $uploadedFile, string $fileGuid, string $fileType, int $userId): File
    {
        $file = new File();
        $file->user_id = $userId;
        $file->fileName = $uploadedFile->getClientOriginalName();
        $file->fileExtension = $uploadedFile->getClientOriginalExtension();
        $file->fileType = $uploadedFile->getClientMimeType();
        $file->fileSize = $uploadedFile->getSize();
        $file->guid = $fileGuid;
        $file->file_type = $fileType;
        $file->processing_type = $fileType;
        $file->uploaded_at = now();
        $file->save();
        
        Log::debug('[FileProcessingService] File record created', [
            'file_id' => $file->id,
            'file_guid' => $fileGuid,
            'file_type' => $fileType,
        ]);
        
        return $file;
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
            $supported = explode(',', env('SUPPORTED_RECEIPT_FORMATS', 'jpg,jpeg,png,gif,bmp,pdf'));
        } else {
            $supported = explode(',', env('SUPPORTED_DOCUMENT_FORMATS', 'doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,pdf,rtf'));
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
            return env('MAX_RECEIPT_SIZE', 10) * 1024 * 1024; // MB to bytes
        } else {
            return env('MAX_DOCUMENT_SIZE', 50) * 1024 * 1024; // MB to bytes
        }
    }
}