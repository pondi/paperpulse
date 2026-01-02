<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    /**
     * Transform file metadata for API responses
     *
     * Single Responsibility: Transform file data only
     * - No S3 paths exposed (internal implementation detail)
     * - No signed URLs (use /files/{id}/content endpoint instead)
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'guid' => $this->guid,
            'name' => $this->fileName ?? $this->original_filename,
            'extension' => $this->fileExtension,
            'mime_type' => $this->fileType ?? $this->mime_type,
            'file_type' => $this->file_type,
            'processing_type' => $this->processing_type,
            'size' => $this->fileSize ?? $this->file_size,
            'status' => $this->status,
            'uploaded_at' => $this->uploaded_at?->toISOString(),
            'has_image_preview' => (bool) $this->has_image_preview,
        ];
    }
}
