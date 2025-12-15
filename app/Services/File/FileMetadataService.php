<?php

namespace App\Services\File;

use App\Contracts\Services\FileMetadataContract;
use App\Models\File;
use App\Services\Files\DocumentMetadataExtractor;
use App\Services\Files\ImageMetadataExtractor;
use App\Services\Files\JobNameGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class FileMetadataService implements FileMetadataContract
{
    protected FileValidationService $validationService;

    public function __construct(FileValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Generate unique job name
     */
    public function generateJobName(): string
    {
        return JobNameGenerator::generate();
    }

    /**
     * Create file record in database from uploaded file
     */
    public function createFileRecordFromUpload(UploadedFile $uploadedFile, string $fileGuid, string $fileType, int $userId, ?string $fileHash = null): File
    {
        // Extract file timestamps from document metadata
        $filePath = $uploadedFile->getRealPath();
        $extension = $uploadedFile->getClientOriginalExtension();
        $dates = DocumentMetadataExtractor::extractDates($filePath, $extension);

        $file = new File;
        $file->user_id = $userId;
        $file->fileName = $uploadedFile->getClientOriginalName();
        $file->fileExtension = $extension;
        $file->fileType = $uploadedFile->getClientMimeType();
        $file->fileSize = $uploadedFile->getSize();
        $file->guid = $fileGuid;
        $file->file_hash = $fileHash;
        $file->file_type = $fileType;
        $file->processing_type = $fileType;
        $file->uploaded_at = now();
        $file->file_created_at = $dates['created_at'];
        $file->file_modified_at = $dates['modified_at'];
        $file->save();

        Log::debug('[FileMetadataService] File record created from upload', [
            'file_id' => $file->id,
            'file_guid' => $fileGuid,
            'file_hash' => $fileHash,
            'file_type' => $fileType,
            'source' => 'upload',
            'file_created_at' => $dates['created_at']?->toDateTimeString(),
            'file_modified_at' => $dates['modified_at']?->toDateTimeString(),
        ]);

        return $file;
    }

    /**
     * Create file record in database from file data array
     */
    public function createFileRecordFromData(array $fileData, string $fileGuid, string $fileType, int $userId, ?string $fileHash = null): File
    {
        $file = new File;
        $file->user_id = $userId;
        $file->fileName = $fileData['fileName'];
        $file->fileExtension = $fileData['extension'];
        $file->fileType = $fileData['mimeType'] ?? $this->validationService->getMimeType($fileData['extension']);
        $file->fileSize = $fileData['size'];
        $file->guid = $fileGuid;
        $file->file_hash = $fileHash;
        $file->file_type = $fileType;
        $file->processing_type = $fileType;
        $file->uploaded_at = now();
        $file->file_created_at = $fileData['file_created_at'] ?? null;
        $file->file_modified_at = $fileData['file_modified_at'] ?? null;
        $file->save();

        Log::debug('[FileMetadataService] File record created from data', [
            'file_id' => $file->id,
            'file_guid' => $fileGuid,
            'file_hash' => $fileHash,
            'file_type' => $fileType,
            'source' => $fileData['source'] ?? 'unknown',
            'file_created_at' => $file->file_created_at?->toDateTimeString(),
            'file_modified_at' => $file->file_modified_at?->toDateTimeString(),
        ]);

        return $file;
    }

    /**
     * Extract file data from uploaded file
     */
    public function extractFileDataFromUpload(UploadedFile $uploadedFile, string $source = 'upload'): array
    {
        $filePath = $uploadedFile->getRealPath();
        $content = file_get_contents($filePath);
        $extension = $uploadedFile->getClientOriginalExtension();

        // Extract file timestamps from document metadata
        $dates = DocumentMetadataExtractor::extractDates($filePath, $extension);

        Log::debug('[FileMetadataService] Extracted file metadata dates', [
            'filename' => $uploadedFile->getClientOriginalName(),
            'extension' => $extension,
            'file_created_at' => $dates['created_at']?->toDateTimeString(),
            'file_modified_at' => $dates['modified_at']?->toDateTimeString(),
            'source' => $source,
        ]);

        return [
            'fileName' => $uploadedFile->getClientOriginalName(),
            'extension' => $extension,
            'mimeType' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
            'content' => $content,
            'source' => $source,
            'file_created_at' => $dates['created_at'],
            'file_modified_at' => $dates['modified_at'],
        ];
    }

    /**
     * Extract file data from PulseDav path
     */
    public function extractFileDataFromPulseDav(string $incomingPath, string $fileContent, int $fileSize): array
    {
        $filename = basename($incomingPath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        // Write content to temp file to extract metadata dates
        $tempPath = sys_get_temp_dir().'/pulsedav_'.uniqid().'.'.$extension;
        file_put_contents($tempPath, $fileContent);

        try {
            // Extract file timestamps from document metadata
            $dates = DocumentMetadataExtractor::extractDates($tempPath, $extension);

            Log::debug('[FileMetadataService] Extracted file metadata dates from PulseDav', [
                'filename' => $filename,
                'extension' => $extension,
                'file_created_at' => $dates['created_at']?->toDateTimeString(),
                'file_modified_at' => $dates['modified_at']?->toDateTimeString(),
                'source' => 'pulsedav',
            ]);
        } finally {
            // Clean up temp file
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        return [
            'fileName' => $filename,
            'extension' => $extension,
            'mimeType' => $this->validationService->getMimeType($extension),
            'size' => $fileSize,
            'content' => $fileContent,
            'source' => 'pulsedav',
            'incomingPath' => $incomingPath,
            'file_created_at' => $dates['created_at'] ?? null,
            'file_modified_at' => $dates['modified_at'] ?? null,
        ];
    }

    /**
     * Prepare file metadata for job processing
     *
     * Note: filePath is optional and may be null in distributed environments.
     * Workers should download from S3 (s3OriginalPath) as needed.
     */
    public function prepareFileMetadata(File $file, string $fileGuid, array $fileData, ?string $workingPath, string $s3Path, string $jobName, array $metadata = []): array
    {
        // Include file dates in metadata for job processing
        $metadata['file_created_at'] = $file->file_created_at?->toISOString();
        $metadata['file_modified_at'] = $file->file_modified_at?->toISOString();

        return [
            'fileId' => $file->id,
            'fileGuid' => $fileGuid,
            'fileName' => $fileData['fileName'],
            'filePath' => $workingPath, // May be null - workers download from S3
            'fileExtension' => $fileData['extension'],
            'fileSize' => $fileData['size'],
            'fileType' => $file->file_type,
            'userId' => $file->user_id,
            's3OriginalPath' => $s3Path,
            'jobName' => $jobName,
            'fileCreatedAt' => $file->file_created_at?->toISOString(),
            'fileModifiedAt' => $file->file_modified_at?->toISOString(),
            'metadata' => $metadata,
        ];
    }

    /**
     * Update file record with S3 path
     */
    public function updateFileWithS3Path(File $file, string $s3Path): void
    {
        $file->s3_original_path = $s3Path;
        $file->save();

        Log::debug('[FileMetadataService] File updated with S3 path', [
            'file_id' => $file->id,
            's3_path' => $s3Path,
        ]);
    }

    /**
     * Extract image metadata (EXIF data, dimensions, etc.)
     */
    public function extractImageMetadata(string $filePath): array
    {
        $metadata = ImageMetadataExtractor::extract($filePath);
        if (empty($metadata)) {
            Log::warning('[FileMetadataService] Failed to extract image metadata', [
                'file_path' => $filePath,
            ]);
        }

        return $metadata;
    }
}
