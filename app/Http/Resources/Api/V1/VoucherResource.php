<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'voucher_type' => $this->voucher_type,
            'code' => $this->code,
            'barcode' => $this->barcode,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'original_value' => $this->original_value,
            'current_value' => $this->current_value,
            'currency' => $this->currency,
            'is_redeemed' => $this->is_redeemed,
            'redeemed_at' => $this->redeemed_at?->toISOString(),
            'redemption_location' => $this->redemption_location,
            'terms_and_conditions' => $this->terms_and_conditions,
            'restrictions' => $this->restrictions,
            'merchant' => $this->when($this->relationLoaded('merchant') && $this->merchant, fn () => [
                'id' => $this->merchant->id,
                'name' => $this->merchant->name,
            ]),
            'tags' => $this->when($this->relationLoaded('tags'), fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
