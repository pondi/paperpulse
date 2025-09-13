<?php

namespace App\Services\Documents;

use App\Services\FileProcessingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class DocumentUploadHandler
{
    public static function processUploads(iterable $uploadedFiles, string $fileType, int $userId, FileProcessingService $fileProcessingService): array
    {
        $processed = [];
        $errors = [];

        foreach ($uploadedFiles as $uploadedFile) {
            if (!($uploadedFile instanceof UploadedFile)) {
                continue;
            }

            $validation = DocumentUploadValidator::validate($uploadedFile);
            if (!$validation['valid']) {
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

