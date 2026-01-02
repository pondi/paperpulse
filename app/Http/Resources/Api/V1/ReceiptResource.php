<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Receipt Resource
 *
 * Single Responsibility: Transform receipt data for API responses
 */
class ReceiptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'merchant' => $this->relationLoaded('merchant') && $this->merchant ? MerchantResource::make($this->merchant) : null,
            'total_amount' => $this->total_amount,
            'tax_amount' => $this->tax_amount,
            'currency' => $this->currency,
            'receipt_date' => $this->receipt_date?->toISOString(),
            'summary' => $this->summary,
            'note' => $this->note,
            'receipt_description' => $this->receipt_description,
            'category' => $this->relationLoaded('category') && $this->category ? CategoryResource::make($this->category) : null,
            'tags' => $this->relationLoaded('tags') && $this->tags ? TagResource::collection($this->tags) : [],
            'line_items' => $this->relationLoaded('lineItems') && $this->lineItems ? LineItemResource::collection($this->lineItems) : [],
        ];
    }
}
