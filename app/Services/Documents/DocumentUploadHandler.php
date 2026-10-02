<?php

namespace App\Services\Documents;

use App\Exceptions\DuplicateFileException;
use App\Services\FileProcessingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Validates and dispatches a set of uploaded files for processing.
 */
class DocumentUploadHandler
{
    /**
     * @param  iterable<int, UploadedFile>  $uploadedFiles
     * @return list<array{index: int, filename: string, status: string, message: string}>
     */
    public static function processUploads(iterable $uploadedFiles, string $fileType, int $userId, FileProcessingService $fileProcessingService, array $metadata = []): array
    {
        $outcomes = [];
        foreach ($uploadedFiles as $index => $uploadedFile) {
            $outcome = ['index' => $index, 'filename' => $uploadedFile->getClientOriginalName()];
            try {
                $validation = DocumentUploadValidator::validate($uploadedFile, $fileType);
                if (! $validation['valid']) {
                    $outcomes[] = $outcome + ['status' => 'failed', 'message' => $validation['error']];

                    continue;
                }

                $fileProcessingService->processUpload($uploadedFile, $fileType, $userId, $metadata);
                $outcomes[] = $outcome + ['status' => 'accepted', 'message' => 'Accepted for processing.'];
            } catch (DuplicateFileException $exception) {
                $outcomes[] = $outcome + [
                    'status' => 'duplicate',
                    'message' => 'Already exists as "'.$exception->getExistingFile()->fileName.'"',
                ];
            } catch (Throwable $exception) {
                Log::error('File upload failed', [
                    'index' => $index, 'user_id' => $userId, 'error' => $exception->getMessage(),
                ]);
                $outcomes[] = $outcome + ['status' => 'failed', 'message' => 'Failed to upload file. Please try again.'];
            }
        }

        return $outcomes;
    }
}
