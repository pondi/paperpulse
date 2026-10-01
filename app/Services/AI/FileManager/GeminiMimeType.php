<?php

namespace App\Services\AI\FileManager;

use App\Exceptions\GeminiApiException;

class GeminiMimeType
{
    public static function validate(string $mime): void
    {
        if (! in_array($mime, config('ai.providers.gemini.supported_mime_types', []), true)) {
            throw new GeminiApiException('Unsupported Gemini MIME type', GeminiApiException::CODE_UNSUPPORTED_MIME, false);
        }
    }

    public static function detect(string $path): string
    {
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        self::validate($mime);
        $expected = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg',
            'txt', 'md' => 'text/plain', 'csv' => 'text/csv', default => null,
        };
        if ($expected !== null && $mime !== $expected && ! ($expected === 'text/csv' && $mime === 'text/plain')) {
            throw new GeminiApiException('File extension does not match detected MIME type', GeminiApiException::CODE_UNSUPPORTED_MIME, false);
        }

        return $mime;
    }
}
