<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invoice Line Item Resource
 *
 * Single Responsibility: Transform invoice line item data for API responses
 */
class InvoiceLineItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'line_number' => $this->line_number,
            'description' => $this->description,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_of_measure' => $this->unit_of_measure,
            'unit_price' => $this->unit_price,
            'discount_percent' => $this->discount_percent,
            'discount_amount' => $this->discount_amount,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'category' => $this->category,
            'notes' => $this->notes,
        ];
    }
}
