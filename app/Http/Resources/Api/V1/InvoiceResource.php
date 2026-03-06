<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
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
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'delivery_date' => $this->delivery_date?->toDateString(),
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
            'merchant' => $this->when($this->relationLoaded('merchant') && $this->merchant, fn () => [
                'id' => $this->merchant->id,
                'name' => $this->merchant->name,
            ]),
            'category' => $this->when($this->relationLoaded('category') && $this->category, fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'color' => $this->category->color,
            ]),
            'line_items' => $this->when($this->relationLoaded('lineItems'), fn () => $this->lineItems->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_amount' => $item->total_amount,
            ])),
            'tags' => $this->when($this->relationLoaded('tags'), fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
