<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * File Detail Resource
 *
 * Single Responsibility: Orchestrate detailed file response with receipt or document data
 * - Returns file metadata + receipt data (for receipts)
 * - Returns file metadata + document data (for documents)
 * - Does NOT include S3 paths or signed URLs
 */
class FileDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'file' => new FileResource($this->resource),
        ];

        // Add receipt data if this is a receipt file
        if ($this->file_type === 'receipt' && $this->receipts->isNotEmpty()) {
            $data['receipt'] = new ReceiptResource($this->receipts->first());
        }

        // Add document data if this is a document file
        if ($this->file_type === 'document' && $this->documents->isNotEmpty()) {
            $data['document'] = new DocumentResource($this->documents->first());
        }

        return $data;
    }
}
