<?php

namespace App\Services\Documents;

use App\Services\File\FileValidationService;
use Illuminate\Http\UploadedFile;

class DocumentUploadValidator
{
    public static function validate(UploadedFile $uploadedFile, string $fileType = 'document'): array
    {
        $result = app(FileValidationService::class)->validateUploadedFile($uploadedFile, $fileType);

        return ['valid' => $result['valid'], 'error' => $result['errors'][0] ?? null];
    }
}
