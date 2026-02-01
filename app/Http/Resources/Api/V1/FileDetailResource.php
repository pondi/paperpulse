<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Document;
use App\Models\Receipt;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * File Detail Resource
 *
 * Single Responsibility: Orchestrate detailed file response with entity data
 * - Returns file metadata + entity data based on primary entity type
 * - Does NOT include S3 paths or signed URLs
 */
class FileDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'file' => new FileResource($this->resource),
        ];

        $primaryEntity = $this->primaryEntity;
        $entity = $primaryEntity?->entity;

        if (! $entity) {
            return $data;
        }

        // Add entity data based on type
        if ($entity instanceof Receipt) {
            $data['receipt'] = new ReceiptResource($entity);
        } elseif ($entity instanceof Document) {
            $data['document'] = new DocumentResource($entity);
        }

        // Add generic entity info for all types
        $data['entity'] = [
            'type' => $primaryEntity->entity_type,
            'id' => $entity->id,
        ];

        return $data;
    }
}
