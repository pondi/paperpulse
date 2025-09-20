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
     * Generate and store image preview for a PDF file.
     *
     * @param  File  $file  File model instance
     * @param  string  $pdfPath  Path to the PDF file
     * @return bool True if preview was generated successfully
     */
    public function generatePreviewForFile(File $file, string $pdfPath): bool
    {
        try {
            // Skip if not a PDF
            if (strtolower($file->fileExtension ?? '') !== 'pdf') {
                Log::info('[FilePreviewManager] Skipping preview - not a PDF', [
                    'file_id' => $file->id,
                    'extension' => $file->fileExtension,
                ]);

                return false;
            }

            // Generate preview image
            $imageData = ImagePreviewGenerator::generateFromPdf($pdfPath);

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
