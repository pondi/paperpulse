<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_statement_id' => $this->bank_statement_id,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'posting_date' => $this->posting_date?->toDateString(),
            'description' => $this->description,
            'reference' => $this->reference,
            'transaction_type' => $this->transaction_type,
            'category' => $this->category,
            'category_group' => $this->category_group?->value,
            'subcategory' => $this->subcategory,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'currency' => $this->currency,
            'counterparty_name' => $this->counterparty_name,
            'counterparty_account' => $this->counterparty_account,
        ];
    }
}
