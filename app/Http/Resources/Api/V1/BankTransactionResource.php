<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bank Transaction Resource
 *
 * Single Responsibility: Transform bank transaction data for API responses
 */
class BankTransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'transaction_date' => $this->transaction_date?->toISOString(),
            'posting_date' => $this->posting_date?->toISOString(),
            'description' => $this->description,
            'reference' => $this->reference,
            'transaction_type' => $this->transaction_type,
            'category' => $this->category,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'currency' => $this->currency,
            'counterparty_name' => $this->counterparty_name,
            'counterparty_account' => $this->counterparty_account,
        ];
    }
}
