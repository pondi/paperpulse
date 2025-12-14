<?php

namespace App\Services\Files;

use App\Models\File;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates image preview generation and storage for files.
 * Single responsibility: Coordinate preview generation workflow.
 */
class FilePreviewManager
{
    protected ImagePreviewStorage $previewStorage;

    public function __construct(ImagePreviewStorage $previewStorage)
    {
        $this->previewStorage = $previewStorage;
    }

    /**
     * Generate and store image preview for a file (PDF or image).
     *
     * @param  File  $file  File model instance
     * @param  string  $filePath  Path to the file (use this file's actual extension, not the original)
     * @return bool True if preview was generated successfully
     */
    public function generatePreviewForFile(File $file, string $filePath): bool
    {
        try {
            // Use the actual file extension from the path (important for converted files)
            $pathExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $extension = $pathExtension ?: strtolower($file->fileExtension ?? '');
            $supportedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

            // Skip if unsupported file type
            if (! in_array($extension, $supportedTypes)) {
                Log::info('[FilePreviewManager] Skipping preview - unsupported file type', [
                    'file_id' => $file->id,
                    'extension' => $extension,
                    'original_extension' => $file->fileExtension,
                    'file_path' => $filePath,
                ]);

                return false;
            }

            // Generate preview image
            $imageData = ImagePreviewGenerator::generatePreview($filePath, $extension);

            // Store preview
            $previewPath = $this->previewStorage->storePreview(
                $imageData,
                $file->user_id,
                $file->guid,
                $file->file_type ?? 'receipt'
            );

            // Update file record
            $file->s3_image_path = $previewPath;
            $file->has_image_preview = true;
            $file->image_generation_error = null;
            $file->save();

            Log::info('[FilePreviewManager] Preview generated successfully', [
                'file_id' => $file->id,
                'guid' => $file->guid,
                'preview_path' => $previewPath,
            ]);

            return true;
        } catch (Exception $e) {
            // Log error and update file record
            Log::error('[FilePreviewManager] Preview generation failed', [
                'file_id' => $file->id,
                'guid' => $file->guid,
                'error' => $e->getMessage(),
            ]);

            $file->has_image_preview = false;
            $file->image_generation_error = substr($e->getMessage(), 0, 1000);
            $file->save();

            return false;
        }
    }

    /**
     * Delete preview for a file.
     *
     * @param  File  $file  File model instance
     * @return bool True if deleted or doesn't exist
     */
    public function deletePreview(File $file): bool
    {
        if (! $file->s3_image_path) {
            return true;
        }

        $deleted = $this->previewStorage->deletePreview($file->s3_image_path);

        if ($deleted) {
            $file->s3_image_path = null;
            $file->has_image_preview = false;
            $file->save();
        }

        return $deleted;
    }
}
