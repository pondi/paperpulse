<?php

namespace App\Services\Documents;

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
     * @return array{processed:array,errors:array}
     */
    public static function processUploads(iterable $uploadedFiles, string $fileType, int $userId, FileProcessingService $fileProcessingService): array
    {
        $processed = [];
        $errors = [];

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

            $processed[] = $fileProcessingService->processUpload($uploadedFile, $fileType, $userId);
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
        ];
    }
}
