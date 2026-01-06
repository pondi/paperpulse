<?php

namespace App\Services\Files;

class FileUploadConfigService
{
    public function getProvider(): string
    {
        return config('ai.file_processing_provider', 'textract+openai');
    }

    public function getMaxSizeMb(string $fileType): int
    {
        $fileType = $this->normalizeFileType($fileType);

        $baseMax = $fileType === 'receipt'
            ? (int) config('processing.documents.max_file_size.receipts', 100)
            : (int) config('processing.documents.max_file_size.documents', 100);

        if ($this->getProvider() === 'gemini') {
            $geminiMax = (int) config('ai.providers.gemini.max_file_size_mb', 50);
            $baseMax = min($baseMax, $geminiMax);
        }

        return $baseMax;
    }

    public function getMaxSizeKb(string $fileType): int
    {
        return $this->getMaxSizeMb($fileType) * 1024;
    }

    public function getMaxSizeBytes(string $fileType): int
    {
        return $this->getMaxSizeMb($fileType) * 1024 * 1024;
    }

    public function getOversizeMessage(string $fileType, ?int $maxSizeMb = null): string
    {
        $fileType = $this->normalizeFileType($fileType);
        $maxSizeMb = $maxSizeMb ?? $this->getMaxSizeMb($fileType);

        if ($this->getProvider() === 'gemini') {
            return "Gemini processing supports files up to {$maxSizeMb}MB. Please upload a smaller file or switch providers.";
        }

        return "File size exceeds maximum limit of {$maxSizeMb}MB for {$fileType}s";
    }

    public function getUploadConfig(): array
    {
        return [
            'provider' => $this->getProvider(),
            'maxFileSizeMb' => [
                'receipt' => $this->getMaxSizeMb('receipt'),
                'document' => $this->getMaxSizeMb('document'),
            ],
        ];
    }

    protected function normalizeFileType(string $fileType): string
    {
        return in_array($fileType, ['receipt', 'document'], true) ? $fileType : 'document';
    }
}
