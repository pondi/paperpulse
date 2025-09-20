<?php

namespace App\Services\OCR;

interface OCRService
{
    /**
     * Extract text from a file
     *
     * @param  string  $filePath  Path to the file
     * @param  string  $fileType  Type of file ('receipt' or 'document')
     * @param  string  $fileGuid  Unique file identifier
     * @param  array  $options  Additional options
     */
    public function extractText(string $filePath, string $fileType, string $fileGuid, array $options = []): OCRResult;

    /**
     * Check if the service can handle the given file type
     */
    public function canHandle(string $filePath): bool;

    /**
     * Get the provider name
     */
    public function getProviderName(): string;

    /**
     * Get supported file extensions
     */
    public function getSupportedExtensions(): array;

    /**
     * Get service capabilities
     */
    public function getCapabilities(): array;
}
