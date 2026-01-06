<?php

namespace App\Services\AI\FileManager;

use App\Exceptions\GeminiApiException;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages file uploads to Gemini Files API.
 *
 * Handles upload-once, reference-many workflow for cost efficiency.
 * Files remain available for ~48 hours after upload.
 */
class GeminiFileManager
{
    protected string $apiKey;

    protected string $uploadEndpoint = 'https://generativelanguage.googleapis.com/upload/v1beta/files';

    protected string $filesEndpoint = 'https://generativelanguage.googleapis.com/v1beta/files';

    public function __construct()
    {
        $this->apiKey = config('ai.providers.gemini.api_key');

        if (! $this->apiKey) {
            throw new Exception('Gemini API key not configured');
        }
    }

    /**
     * Upload file to Gemini Files API.
     *
     * @param  string  $localPath  Absolute path to local file
     * @param  string|null  $displayName  Optional display name
     * @return array {fileUri: string, name: string, mimeType: string, sizeBytes: int}
     *
     * @throws GeminiApiException
     */
    public function uploadFile(string $localPath, ?string $displayName = null): array
    {
        if (! file_exists($localPath)) {
            throw new Exception("File not found: {$localPath}");
        }

        $fileName = $displayName ?? basename($localPath);
        $mimeType = mime_content_type($localPath) ?: 'application/octet-stream';
        $fileSize = filesize($localPath);

        Log::info('[GeminiFileManager] Uploading file to Gemini Files API', [
            'file_path' => $localPath,
            'display_name' => $fileName,
            'mime_type' => $mimeType,
            'size_bytes' => $fileSize,
        ]);

        try {
            // Step 1: Start resumable upload session
            $startResponse = Http::timeout(120)
                ->withHeaders([
                    'X-Goog-Upload-Protocol' => 'resumable',
                    'X-Goog-Upload-Command' => 'start',
                    'X-Goog-Upload-Header-Content-Length' => (string) $fileSize,
                    'X-Goog-Upload-Header-Content-Type' => $mimeType,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->uploadEndpoint}?key={$this->apiKey}", [
                    'file' => [
                        'display_name' => $fileName,
                    ],
                ]);

            if (! $startResponse->successful()) {
                throw new GeminiApiException(
                    'Gemini Files API start upload failed: '.$startResponse->body(),
                    GeminiApiException::CODE_API_ERROR,
                    true,
                    ['status' => $startResponse->status(), 'body' => $startResponse->body()]
                );
            }

            // Extract upload URL from response header
            $uploadUrl = $startResponse->header('X-Goog-Upload-URL');
            if (! $uploadUrl) {
                throw new GeminiApiException(
                    'No upload URL returned from Gemini Files API',
                    GeminiApiException::CODE_PARSE_ERROR,
                    false,
                    ['headers' => $startResponse->headers()]
                );
            }

            Log::debug('[GeminiFileManager] Resumable upload session started', [
                'upload_url' => $uploadUrl,
            ]);

            // Step 2: Upload file content
            $fileContent = file_get_contents($localPath);
            $uploadResponse = Http::timeout(120)
                ->withHeaders([
                    'Content-Length' => (string) $fileSize,
                    'X-Goog-Upload-Offset' => '0',
                    'X-Goog-Upload-Command' => 'upload, finalize',
                ])
                ->withBody($fileContent, $mimeType)
                ->post($uploadUrl);

            if (! $uploadResponse->successful()) {
                throw new GeminiApiException(
                    'Gemini Files API upload failed: '.$uploadResponse->body(),
                    GeminiApiException::CODE_API_ERROR,
                    true,
                    ['status' => $uploadResponse->status(), 'body' => $uploadResponse->body()]
                );
            }

            $data = $uploadResponse->json();
            $file = $data['file'] ?? null;

            if (! $file) {
                throw new GeminiApiException(
                    'Invalid response from Gemini Files API',
                    GeminiApiException::CODE_PARSE_ERROR,
                    false,
                    ['response' => $data]
                );
            }

            Log::info('[GeminiFileManager] File uploaded successfully', [
                'file_uri' => $file['uri'] ?? null,
                'file_name' => $file['name'] ?? null,
                'file_size' => $file['sizeBytes'] ?? null,
            ]);

            return [
                'fileUri' => $file['uri'],
                'name' => $file['name'],
                'mimeType' => $file['mimeType'] ?? $mimeType,
                'sizeBytes' => $file['sizeBytes'] ?? $fileSize,
            ];

        } catch (GeminiApiException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new GeminiApiException(
                'Gemini file upload failed: '.$e->getMessage(),
                GeminiApiException::CODE_API_ERROR,
                true,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Delete file from Gemini Files API.
     *
     * @param  string  $fileName  File name from upload response (e.g., "files/abc123")
     */
    public function deleteFile(string $fileName): bool
    {
        Log::info('[GeminiFileManager] Deleting file from Gemini Files API', [
            'file_name' => $fileName,
        ]);

        try {
            $response = Http::timeout(30)
                ->delete("{$this->filesEndpoint}/{$fileName}?key={$this->apiKey}");

            if ($response->successful()) {
                Log::info('[GeminiFileManager] File deleted successfully', [
                    'file_name' => $fileName,
                ]);

                return true;
            }

            // If file doesn't exist (404), consider it success
            if ($response->status() === 404) {
                Log::warning('[GeminiFileManager] File not found (already deleted?)', [
                    'file_name' => $fileName,
                ]);

                return true;
            }

            Log::warning('[GeminiFileManager] File deletion failed', [
                'file_name' => $fileName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('[GeminiFileManager] File deletion error', [
                'file_name' => $fileName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get file metadata from Gemini Files API.
     *
     * @param  string  $fileName  File name (e.g., "files/abc123")
     */
    public function getFileMetadata(string $fileName): ?array
    {
        try {
            $response = Http::timeout(30)
                ->get("{$this->filesEndpoint}/{$fileName}?key={$this->apiKey}");

            if (! $response->successful()) {
                return null;
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('[GeminiFileManager] Failed to get file metadata', [
                'file_name' => $fileName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
