<?php

namespace App\Contracts\Services;

use App\Models\File;
use Illuminate\Http\UploadedFile;

interface FileMetadataContract
{
    /**
     * Generate unique job name
     */
    public function generateJobName(): string;

    /**
     * Create file record in database from uploaded file
     */
    public function createFileRecordFromUpload(UploadedFile $uploadedFile, string $fileGuid, string $fileType, int $userId): File;

    /**
     * Create file record in database from file data array
     */
    public function createFileRecordFromData(array $fileData, string $fileGuid, string $fileType, int $userId): File;

    /**
     * Extract file data from uploaded file
     */
    public function extractFileDataFromUpload(UploadedFile $uploadedFile, string $source = 'upload'): array;

    /**
     * Extract file data from PulseDav path
     */
    public function extractFileDataFromPulseDav(string $incomingPath, string $fileContent, int $fileSize): array;

    /**
     * Prepare file metadata for job processing
     */
    public function prepareFileMetadata(File $file, string $fileGuid, array $fileData, ?string $workingPath, string $s3Path, string $jobName, array $metadata = []): array;

    /**
     * Update file record with S3 path
     */
    public function updateFileWithS3Path(File $file, string $s3Path): void;

    /**
     * Extract image metadata (EXIF data, dimensions, etc.)
     */
    public function extractImageMetadata(string $filePath): array;
}
