<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contract Resource
 *
 * Single Responsibility: Transform contract data for API responses
 */
class ContractResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'contract_title' => $this->contract_title,
            'contract_type' => $this->contract_type,
            'parties' => $this->parties,
            'effective_date' => $this->effective_date?->toISOString(),
            'expiry_date' => $this->expiry_date?->toISOString(),
            'signature_date' => $this->signature_date?->toISOString(),
            'duration' => $this->duration,
            'renewal_terms' => $this->renewal_terms,
            'termination_conditions' => $this->termination_conditions,
            'contract_value' => $this->contract_value,
            'currency' => $this->currency,
            'payment_schedule' => $this->payment_schedule,
            'governing_law' => $this->governing_law,
            'jurisdiction' => $this->jurisdiction,
            'status' => $this->status,
            'key_terms' => $this->key_terms,
            'obligations' => $this->obligations,
            'summary' => $this->summary,
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
