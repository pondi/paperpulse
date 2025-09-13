<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseApiResource extends JsonResource
{
    /**
     * Common fields for all resources
     */
    protected function commonFields(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Include user ownership if model has user_id
     */
    protected function ownershipField(): array
    {
        if (isset($this->user_id)) {
            return ['owner_id' => $this->user_id];
        }

        return [];
    }
}
