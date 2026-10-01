<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\HeaderUtils;

class UploadedContent
{
    /** @return array<string, string> */
    public static function headers(string $extension, string $filename, string $disposition = 'inline'): array
    {
        $contentType = match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'tif', 'tiff' => 'image/tiff',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain; charset=utf-8',
            'csv' => 'text/csv; charset=utf-8',
            default => 'application/octet-stream',
        };
        if ($contentType === 'application/octet-stream') {
            $disposition = 'attachment';
        }
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'file';

        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename),
            'Content-Security-Policy' => "sandbox; default-src 'none'; form-action 'none'; base-uri 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
    }
}
