<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Return Policy Resource
 *
 * Single Responsibility: Transform return policy data for API responses
 */
class ReturnPolicyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'return_deadline' => $this->return_deadline?->toISOString(),
            'exchange_deadline' => $this->exchange_deadline?->toISOString(),
            'conditions' => $this->conditions,
            'refund_method' => $this->refund_method,
            'restocking_fee' => $this->restocking_fee,
            'restocking_fee_percentage' => $this->restocking_fee_percentage,
            'is_final_sale' => $this->is_final_sale,
            'requires_receipt' => $this->requires_receipt,
            'requires_original_packaging' => $this->requires_original_packaging,
            'merchant' => $this->whenLoaded('merchant', fn () => MerchantResource::make($this->merchant)),
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
