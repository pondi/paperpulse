<?php

namespace App\Services\Documents;

use App\Exceptions\DuplicateFileException;
use App\Services\FileProcessingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
        $formats = $fileType === 'document'
            ? 'jpeg,png,jpg,pdf,tiff,tif,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,rtf,txt,html,csv'
            : 'jpeg,png,jpg,pdf,tiff,tif';

        foreach ($uploadedFiles as $index => $uploadedFile) {
            $outcome = ['index' => $index, 'filename' => $uploadedFile->getClientOriginalName()];
            try {
                $validator = Validator::make(['file' => $uploadedFile], [
                    'file' => "required|file|mimes:{$formats}|max:102400",
                ]);
                $validation = $validator->fails()
                    ? ['valid' => false, 'error' => $validator->errors()->first('file')]
                    : DocumentUploadValidator::validate($uploadedFile);
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
