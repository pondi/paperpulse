<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Voucher Resource
 *
 * Single Responsibility: Transform voucher data for API responses
 */
class VoucherResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'voucher_type' => $this->voucher_type,
            'code' => $this->code,
            'barcode' => $this->barcode,
            'qr_code' => $this->qr_code,
            'issue_date' => $this->issue_date?->toISOString(),
            'expiry_date' => $this->expiry_date?->toISOString(),
            'original_value' => $this->original_value,
            'current_value' => $this->current_value,
            'currency' => $this->currency,
            'installment_count' => $this->installment_count,
            'monthly_payment' => $this->monthly_payment,
            'first_payment_date' => $this->first_payment_date?->toISOString(),
            'final_payment_date' => $this->final_payment_date?->toISOString(),
            'is_redeemed' => $this->is_redeemed,
            'redeemed_at' => $this->redeemed_at?->toISOString(),
            'redemption_location' => $this->redemption_location,
            'terms_and_conditions' => $this->terms_and_conditions,
            'restrictions' => $this->restrictions,
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'merchant' => $this->whenLoaded('merchant', fn () => MerchantResource::make($this->merchant)),
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
