<?php

namespace App\Services\Files;

use App\Services\Documents\ConversionCapabilities;

class FileUploadConfigService
{
    public const MIME_TYPES = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'tif' => ['image/tiff'], 'tiff' => ['image/tiff'], 'pdf' => ['application/pdf'],
        'txt' => ['text/plain'], 'html' => ['text/html'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
    ];

    public function getProvider(): string
    {
        return config('ai.file_processing_provider', 'textract+openai');
    }

    public function getMaxSizeMb(string $fileType): int
    {
        $baseMax = (int) config('processing.documents.max_file_size.'.($fileType === 'receipt' ? 'receipts' : 'documents'));
        $providerMax = $this->getProvider() === 'gemini' ? (int) config('ai.providers.gemini.max_file_size_mb', 50) : 10;

        return min($baseMax, $providerMax);
    }

    public function getMaxSizeKb(string $fileType): int
    {
        return $this->getMaxSizeMb($fileType) * 1024;
    }

    public function getMaxSizeBytes(string $fileType, ?string $extension = null): int
    {
        $max = $this->getMaxSizeMb($fileType) * 1024 * 1024;
        if (isset(ConversionCapabilities::OFFICE_MIME_TYPES[strtolower($extension ?? '')])) {
            $max = min($max, config('processing.conversion.max_input_bytes'));
        }

        return $max;
    }

    /** @return array<string, array{mimeTypes: list<string>, maxBytes: int}> */
    public function getCapabilities(string $fileType): array
    {
        $formats = config('processing.documents.supported_formats.'.($fileType === 'receipt' ? 'receipts' : 'documents'));
        $capabilities = [];
        foreach ($formats as $extension) {
            $officeMime = ConversionCapabilities::OFFICE_MIME_TYPES[$extension] ?? null;
            if ($officeMime !== null) {
                if (! config('processing.conversion.enabled') || config('processing.conversion.driver') !== 'local') {
                    continue;
                }
                $mimeTypes = [$officeMime];
            } else {
                $mimeTypes = self::MIME_TYPES[$extension] ?? [];
                if ($this->getProvider() !== 'gemini' && in_array($extension, ['txt', 'html'], true)) {
                    continue;
                }
                if ($this->getProvider() === 'gemini' && ! array_intersect($mimeTypes, config('ai.providers.gemini.supported_mime_types'))) {
                    continue;
                }
            }
            if ($mimeTypes !== []) {
                $capabilities[$extension] = ['mimeTypes' => $mimeTypes, 'maxBytes' => $this->getMaxSizeBytes($fileType, $extension)];
            }
        }

        return $capabilities;
    }

    public function getOversizeMessage(string $fileType, ?int $maxSizeMb = null): string
    {
        $maxSizeMb ??= $this->getMaxSizeMb($fileType);

        return "File size exceeds maximum limit of {$maxSizeMb}MB for {$fileType}s";
    }

    public function getUploadConfig(): array
    {
        return [
            'provider' => $this->getProvider(),
            'maxFileSizeMb' => ['receipt' => $this->getMaxSizeMb('receipt'), 'document' => $this->getMaxSizeMb('document')],
            'capabilities' => ['receipt' => $this->getCapabilities('receipt'), 'document' => $this->getCapabilities('document')],
        ];
    }
}
