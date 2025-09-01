<?php

namespace App\Services\File;

use App\Contracts\Services\FileMetadataContract;
use App\Models\File;
use Exception;
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
        $adjectives = ['swift', 'bright', 'stellar', 'cosmic', 'quantum', 'digital', 'cyber', 'turbo', 'mega', 'ultra'];
        $nouns = ['pulse', 'wave', 'stream', 'flow', 'burst', 'beam', 'spark', 'flash', 'surge', 'blast'];

        $adjective = $adjectives[array_rand($adjectives)];
        $noun = $nouns[array_rand($nouns)];

        return "{$adjective}-{$noun}-".substr(md5(microtime()), rand(0, 26), 5);
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
        $metadata = [];

        try {
            // Get basic image info
            $imageInfo = getimagesize($filePath);
            if ($imageInfo !== false) {
                $metadata['width'] = $imageInfo[0];
                $metadata['height'] = $imageInfo[1];
                $metadata['mime_type'] = $imageInfo['mime'];
                $metadata['bits'] = $imageInfo['bits'] ?? null;
                $metadata['channels'] = $imageInfo['channels'] ?? null;
            }

            // Try to get EXIF data for JPEG images
            if (function_exists('exif_read_data') && in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg'])) {
                $exifData = @exif_read_data($filePath);
                if ($exifData !== false) {
                    $metadata['exif'] = [
                        'camera_make' => $exifData['Make'] ?? null,
                        'camera_model' => $exifData['Model'] ?? null,
                        'date_taken' => $exifData['DateTime'] ?? null,
                        'gps_latitude' => $this->extractGPSCoordinate($exifData, 'GPSLatitude', 'GPSLatitudeRef'),
                        'gps_longitude' => $this->extractGPSCoordinate($exifData, 'GPSLongitude', 'GPSLongitudeRef'),
                    ];
                }
            }
        } catch (Exception $e) {
            Log::warning('[FileMetadataService] Failed to extract image metadata', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }

        return $metadata;
    }

    /**
     * Extract GPS coordinate from EXIF data
     */
    protected function extractGPSCoordinate(array $exifData, string $coordinateKey, string $refKey): ?float
    {
        if (! isset($exifData[$coordinateKey]) || ! isset($exifData[$refKey])) {
            return null;
        }

        $coordinates = $exifData[$coordinateKey];
        $ref = $exifData[$refKey];

        if (! is_array($coordinates) || count($coordinates) !== 3) {
            return null;
        }

        $decimal = $this->convertDMSToDecimal($coordinates[0], $coordinates[1], $coordinates[2]);

        if (in_array($ref, ['S', 'W'])) {
            $decimal *= -1;
        }

        return $decimal;
    }

    /**
     * Convert DMS (Degrees, Minutes, Seconds) to decimal
     */
    protected function convertDMSToDecimal($degrees, $minutes, $seconds): float
    {
        $degreesDecimal = $this->fractionToDecimal($degrees);
        $minutesDecimal = $this->fractionToDecimal($minutes);
        $secondsDecimal = $this->fractionToDecimal($seconds);

        return $degreesDecimal + ($minutesDecimal / 60) + ($secondsDecimal / 3600);
    }

    /**
     * Convert fraction string to decimal
     */
    protected function fractionToDecimal($fraction): float
    {
        if (is_numeric($fraction)) {
            return (float) $fraction;
        }

        if (strpos($fraction, '/') !== false) {
            $parts = explode('/', $fraction);
            if (count($parts) === 2 && $parts[1] != 0) {
                return (float) $parts[0] / (float) $parts[1];
            }
        }

        return 0;
    }
}
