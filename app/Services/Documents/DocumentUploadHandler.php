<?php

namespace App\Services\Documents;

use App\Exceptions\DuplicateFileException;
use App\Services\FileProcessingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Validates and dispatches a set of uploaded files for processing.
 */
class DocumentUploadHandler
{
    /**
     * Validate and process uploaded files via FileProcessingService.
     *
     * @param  iterable  $uploadedFiles  Iterable of UploadedFile
     * @param  string  $fileType  'receipt' or 'document'
     * @return array{processed:array,errors:array,duplicates:array}
     */
    public static function processUploads(iterable $uploadedFiles, string $fileType, int $userId, FileProcessingService $fileProcessingService, array $metadata = []): array
    {
        $processed = [];
        $errors = [];
        $duplicates = [];

        foreach ($uploadedFiles as $uploadedFile) {
            if (! ($uploadedFile instanceof UploadedFile)) {
                continue;
            }

            $validation = DocumentUploadValidator::validate($uploadedFile);
            if (! $validation['valid']) {
                $filename = $uploadedFile->getClientOriginalName();
                $errors[$filename] = $validation['error'];
                Log::error('File validation failed', [
                    'filename' => $filename,
                    'error' => $validation['error'],
                ]);

                continue;
            }

            try {
                $processed[] = $fileProcessingService->processUpload($uploadedFile, $fileType, $userId, $metadata);
            } catch (DuplicateFileException $e) {
                // Duplicate files are not errors - they're skipped
                $filename = $uploadedFile->getClientOriginalName();
                Log::info('Duplicate file skipped during upload', [
                    'filename' => $filename,
                    'file_hash' => $e->getFileHash(),
                    'existing_file_id' => $e->getExistingFile()->id,
                ]);

                // Add to duplicates array for user notification
                $duplicates[$filename] = [
                    'message' => 'Already exists as "'.$e->getExistingFile()->fileName.'"',
                    'existing_file' => $e->getExistingFile(),
                ];
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
            'duplicates' => $duplicates,
        ];
    }
}
