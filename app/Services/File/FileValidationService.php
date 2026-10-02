<?php

namespace App\Services\File;

use App\Contracts\Services\FileValidationContract;
use App\Services\Documents\ConversionCapabilities;
use App\Services\Files\FileUploadConfigService;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class FileValidationService implements FileValidationContract
{
    public function __construct(private FileUploadConfigService $config) {}

    public function isSupported(string $extension, string $fileType): bool
    {
        return isset($this->config->getCapabilities($fileType)[strtolower($extension)]);
    }

    public function getMaxFileSize(string $fileType): int
    {
        return $this->config->getMaxSizeBytes($fileType);
    }

    public function validateUploadedFile(UploadedFile $file, string $fileType): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! $file->isValid() || $file->getSize() > $this->config->getMaxSizeBytes($fileType, $extension)) {
            return ['valid' => false, 'errors' => ['Upload is invalid or exceeds the processing size limit.']];
        }

        return $this->validateFileData([
            'fileName' => $file->getClientOriginalName(), 'extension' => $extension,
            'size' => $file->getSize(), 'content' => $file->getContent(),
        ], $fileType);
    }

    public function validateFileData(array $fileData, string $fileType): array
    {
        foreach (['fileName', 'extension', 'size', 'content'] as $field) {
            if (! isset($fileData[$field]) || $fileData[$field] === '') {
                return ['valid' => false, 'errors' => ["Missing required field: {$field}"]];
            }
        }
        $extension = strtolower($fileData['extension']);
        $capability = $this->config->getCapabilities($fileType)[$extension] ?? null;
        if ($capability === null) {
            return ['valid' => false, 'errors' => ["File type '{$extension}' is not supported for {$fileType}s"]];
        }
        $content = $fileData['content'];
        $size = strlen($content);
        if ($size === 0 || $size > $capability['maxBytes'] || $size !== (int) $fileData['size']) {
            return ['valid' => false, 'errors' => ['File is empty, exceeds the processing size limit, or has an incorrect declared size.']];
        }
        if (strtolower(pathinfo($fileData['fileName'], PATHINFO_EXTENSION)) !== $extension) {
            return ['valid' => false, 'errors' => ['Filename extension does not match the declared extension.']];
        }
        if (isset(ConversionCapabilities::OFFICE_MIME_TYPES[$extension])) {
            $path = tempnam(sys_get_temp_dir(), 'upload-validation-');
            try {
                file_put_contents($path, $content);
                ConversionCapabilities::detect($path, $extension);
            } catch (RuntimeException $exception) {
                return ['valid' => false, 'errors' => [$exception->getMessage()]];
            } finally {
                unlink($path);
            }
        } else {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
            if (! in_array($mime, $capability['mimeTypes'], true)
                || ($extension === 'pdf' && ! str_starts_with($content, '%PDF-'))
                || (str_starts_with($mime, 'image/') && @getimagesizefromstring($content) === false)) {
                return ['valid' => false, 'errors' => ['File bytes do not match its extension or are corrupted.']];
            }
        }

        return ['valid' => true, 'errors' => []];
    }

    public function getMimeType(string $extension): string
    {
        return ConversionCapabilities::OFFICE_MIME_TYPES[strtolower($extension)]
            ?? FileUploadConfigService::MIME_TYPES[strtolower($extension)][0] ?? 'application/octet-stream';
    }
}
