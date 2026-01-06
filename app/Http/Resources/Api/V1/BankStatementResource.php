<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bank Statement Resource
 *
 * Single Responsibility: Transform bank statement data for API responses
 */
class BankStatementResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'account_holder_name' => $this->account_holder_name,
            'account_number' => $this->account_number,
            'iban' => $this->iban,
            'swift_code' => $this->swift_code,
            'statement_date' => $this->statement_date?->toISOString(),
            'statement_period_start' => $this->statement_period_start?->toISOString(),
            'statement_period_end' => $this->statement_period_end?->toISOString(),
            'opening_balance' => $this->opening_balance,
            'closing_balance' => $this->closing_balance,
            'currency' => $this->currency,
            'total_credits' => $this->total_credits,
            'total_debits' => $this->total_debits,
            'transaction_count' => $this->transaction_count,
            'transactions' => $this->whenLoaded('transactions', fn () => BankTransactionResource::collection($this->transactions)),
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
