<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * LineItem Resource
 *
 * Single Responsibility: Transform line item data for API responses
 */
class LineItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->text,
            'amount' => $this->amount,
            'quantity' => $this->qty,
            'unit_price' => $this->unit_price,
        ];
    }
}
