<?php

namespace App\Services\Files;

use App\Services\StorageService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Handles storage of image preview files.
 * Single responsibility: Store and retrieve image previews.
 */
class ImagePreviewStorage
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Store an image preview for a file.
     *
     * @param  string  $imageData  Binary image data
     * @param  int  $userId  User ID
     * @param  string  $guid  File GUID
     * @param  string  $fileType  'receipt' or 'document'
     * @return string Storage path of the preview
     *
     * @throws Exception If storage fails
     */
    public function storePreview(string $imageData, int $userId, string $guid, string $fileType): string
    {
        try {
            $path = $this->storageService->storeFile(
                $imageData,
                $userId,
                $guid,
                $fileType,
                'preview',
                'jpg'
            );

            Log::info('[ImagePreviewStorage] Preview stored successfully', [
                'user_id' => $userId,
                'guid' => $guid,
                'file_type' => $fileType,
                'path' => $path,
                'size' => strlen($imageData),
            ]);

            return $path;
        } catch (Exception $e) {
            Log::error('[ImagePreviewStorage] Failed to store preview', [
                'user_id' => $userId,
                'guid' => $guid,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete an image preview.
     *
     * @param  string  $path  Storage path of the preview
     * @return bool True if deleted or doesn't exist
     */
    public function deletePreview(string $path): bool
    {
        return $this->storageService->deleteFile($path);
    }

    /**
     * Get preview URL for serving.
     *
     * @param  string  $path  Storage path of the preview
     * @param  int  $expirationMinutes  URL expiration time
     * @return string|null Temporary URL or null if not found
     */
    public function getPreviewUrl(string $path, int $expirationMinutes = 60): ?string
    {
        return $this->storageService->getTemporaryUrl($path, $expirationMinutes);
    }
}
