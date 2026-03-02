<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'posting_date' => $this->posting_date?->format('Y-m-d'),
            'description' => $this->description,
            'reference' => $this->reference,
            'transaction_type' => $this->transaction_type,
            'category' => $this->category,
            'category_group' => $this->category_group?->value,
            'category_group_label' => $this->category_group?->label(),
            'subcategory' => $this->subcategory,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'currency' => $this->currency,
            'counterparty_name' => $this->counterparty_name,
            'counterparty_account' => $this->counterparty_account,
        ];
    }
}
