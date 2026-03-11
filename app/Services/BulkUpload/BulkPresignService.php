<?php

declare(strict_types=1);

namespace App\Services\BulkUpload;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generates presigned PUT URLs for direct S3 uploads from the Uplink client.
 */
class BulkPresignService
{
    private const PRESIGN_EXPIRY_MINUTES = 30;

    /**
     * Generate a presigned PUT URL for uploading a file directly to S3.
     *
     * @return array{url: string, expires_at: Carbon, headers: array<string, string>}
     */
    public function generatePutUrl(string $s3Key, string $mimeType): array
    {
        $expiresAt = now()->addMinutes(self::PRESIGN_EXPIRY_MINUTES);

        $result = Storage::disk('uplink')->temporaryUploadUrl(
            $s3Key,
            $expiresAt,
            ['ContentType' => $mimeType],
        );

        Log::debug('[BulkPresign] Generated presigned PUT URL', [
            's3_key' => $s3Key,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return [
            'url' => $result['url'],
            'expires_at' => $expiresAt,
            'headers' => array_merge(
                $this->flattenHeaders($result['headers']),
                ['Content-Type' => $mimeType],
            ),
        ];
    }

    /**
     * Flatten multi-value headers into single-value strings.
     *
     * AWS SDK returns headers as ['Header' => ['value']], but clients need ['Header' => 'value'].
     *
     * @param  array<string, array<string>|string>  $headers
     * @return array<string, string>
     */
    private function flattenHeaders(array $headers): array
    {
        $flat = [];

        foreach ($headers as $key => $value) {
            $flat[$key] = is_array($value) ? implode(', ', $value) : $value;
        }

        return $flat;
    }
}
