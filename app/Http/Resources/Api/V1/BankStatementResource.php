<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankStatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'bank_name' => $this->bank_name,
            'account_holder_name' => $this->account_holder_name,
            'account_number' => $this->account_number,
            'iban' => $this->iban,
            'swift_code' => $this->swift_code,
            'statement_date' => $this->statement_date?->toDateString(),
            'statement_period_start' => $this->statement_period_start?->toDateString(),
            'statement_period_end' => $this->statement_period_end?->toDateString(),
            'opening_balance' => $this->opening_balance,
            'closing_balance' => $this->closing_balance,
            'currency' => $this->currency,
            'total_credits' => $this->total_credits,
            'total_debits' => $this->total_debits,
            'transaction_count' => $this->transaction_count,
            'transactions' => $this->when($this->relationLoaded('transactions'), fn () => BankTransactionResource::collection($this->transactions)),
            'tags' => $this->when($this->relationLoaded('tags'), fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
