<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LineItem;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * LineItem Resource
 *
 * Single Responsibility: Transform line item data for API responses
 *
 * @mixin LineItem
 */
class LineItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->text,
            'amount' => $this->total,
            'quantity' => $this->qty,
            'unit_price' => $this->price,
        ];
    }
}
