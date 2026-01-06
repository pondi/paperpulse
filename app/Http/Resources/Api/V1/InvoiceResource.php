<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invoice Resource
 *
 * Single Responsibility: Transform invoice data for API responses
 */
class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_type' => $this->invoice_type,
            'from_name' => $this->from_name,
            'from_address' => $this->from_address,
            'from_vat_number' => $this->from_vat_number,
            'from_email' => $this->from_email,
            'from_phone' => $this->from_phone,
            'to_name' => $this->to_name,
            'to_address' => $this->to_address,
            'to_vat_number' => $this->to_vat_number,
            'to_email' => $this->to_email,
            'to_phone' => $this->to_phone,
            'invoice_date' => $this->invoice_date?->toISOString(),
            'due_date' => $this->due_date?->toISOString(),
            'delivery_date' => $this->delivery_date?->toISOString(),
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'shipping_amount' => $this->shipping_amount,
            'total_amount' => $this->total_amount,
            'amount_paid' => $this->amount_paid,
            'amount_due' => $this->amount_due,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_terms' => $this->payment_terms,
            'purchase_order_number' => $this->purchase_order_number,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'merchant' => $this->whenLoaded('merchant', fn () => MerchantResource::make($this->merchant)),
            'category' => $this->whenLoaded('category', fn () => CategoryResource::make($this->category)),
            'line_items' => $this->whenLoaded('lineItems', fn () => InvoiceLineItemResource::collection($this->lineItems)),
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
