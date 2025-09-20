<?php

namespace App\Services\File;

use App\Contracts\Services\FileMetadataContract;
use App\Models\File;
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
    public function createFileRecordFromUpload(UploadedFile $uploadedFile, string $fileGuid, string $fileType, int $userId): File
    {
        $file = new File;
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

        Log::debug('[FileMetadataService] File record created from upload', [
            'file_id' => $file->id,
            'file_guid' => $fileGuid,
            'file_type' => $fileType,
            'source' => 'upload',
        ]);

        return $file;
    }

    /**
     * Create file record in database from file data array
     */
    public function createFileRecordFromData(array $fileData, string $fileGuid, string $fileType, int $userId): File
    {
        $file = new File;
        $file->user_id = $userId;
        $file->fileName = $fileData['fileName'];
        $file->fileExtension = $fileData['extension'];
        $file->fileType = $fileData['mimeType'] ?? $this->validationService->getMimeType($fileData['extension']);
        $file->fileSize = $fileData['size'];
        $file->guid = $fileGuid;
        $file->file_type = $fileType;
        $file->processing_type = $fileType;
        $file->uploaded_at = now();
        $file->save();

        Log::debug('[FileMetadataService] File record created from data', [
            'file_id' => $file->id,
            'file_guid' => $fileGuid,
            'file_type' => $fileType,
            'source' => $fileData['source'] ?? 'unknown',
        ]);

        return $file;
    }

    /**
     * Extract file data from uploaded file
     */
    public function extractFileDataFromUpload(UploadedFile $uploadedFile, string $source = 'upload'): array
    {
        $content = file_get_contents($uploadedFile->getRealPath());

        return [
            'fileName' => $uploadedFile->getClientOriginalName(),
            'extension' => $uploadedFile->getClientOriginalExtension(),
            'mimeType' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
            'content' => $content,
            'source' => $source,
        ];
    }

    /**
     * Extract file data from PulseDav path
     */
    public function extractFileDataFromPulseDav(string $incomingPath, string $fileContent, int $fileSize): array
    {
        $filename = basename($incomingPath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return [
            'fileName' => $filename,
            'extension' => $extension,
            'mimeType' => $this->validationService->getMimeType($extension),
            'size' => $fileSize,
            'content' => $fileContent,
            'source' => 'pulsedav',
            'incomingPath' => $incomingPath,
        ];
    }

    /**
     * Prepare file metadata for job processing
     */
    public function prepareFileMetadata(File $file, string $fileGuid, array $fileData, string $workingPath, string $s3Path, string $jobName, array $metadata = []): array
    {
        return [
            'fileId' => $file->id,
            'fileGuid' => $fileGuid,
            'fileName' => $fileData['fileName'],
            'filePath' => $workingPath,
            'fileExtension' => $fileData['extension'],
            'fileSize' => $fileData['size'],
            'fileType' => $file->file_type,
            'userId' => $file->user_id,
            's3OriginalPath' => $s3Path,
            'jobName' => $jobName,
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
