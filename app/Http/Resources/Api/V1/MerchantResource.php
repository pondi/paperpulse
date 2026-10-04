<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Merchant;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Merchant Resource
 *
 * Single Responsibility: Transform merchant data for API responses
 *
 * @mixin Merchant
 */
class MerchantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
