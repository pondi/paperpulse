<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Warranty Resource
 *
 * Single Responsibility: Transform warranty data for API responses
 */
class WarrantyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'product_category' => $this->product_category,
            'manufacturer' => $this->manufacturer,
            'model_number' => $this->model_number,
            'serial_number' => $this->serial_number,
            'purchase_date' => $this->purchase_date,
            'warranty_start_date' => $this->warranty_start_date,
            'warranty_end_date' => $this->warranty_end_date,
            'warranty_duration' => $this->warranty_duration,
            'warranty_type' => $this->warranty_type,
            'warranty_provider' => $this->warranty_provider,
            'warranty_number' => $this->warranty_number,
            'coverage_type' => $this->coverage_type,
            'coverage_description' => $this->coverage_description,
            'exclusions' => $this->exclusions,
            'support_phone' => $this->support_phone,
            'support_email' => $this->support_email,
            'support_website' => $this->support_website,
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
