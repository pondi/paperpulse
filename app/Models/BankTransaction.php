<?php

namespace App\Models;

use App\Enums\DeletedReason;
use App\Enums\TransactionCategory;
use App\Services\BankStatements\TransactionCategorizationService;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class BankTransaction extends Model
{
    use BelongsToUser;
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'bank_statement_id',
        'transaction_date',
        'posting_date',
        'description',
        'reference',
        'transaction_type',
        'category',
        'category_group',
        'category_source',
        'subcategory',
        'amount',
        'balance_after',
        'currency',
        'counterparty_name',
        'counterparty_account',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'posting_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'category_group' => TransactionCategory::class,
        'deleted_reason' => DeletedReason::class,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $transaction): void {
            if (auth()->id() === $transaction->user_id && $transaction->isDirty(['category_group', 'subcategory']) && ! $transaction->isDirty('category_source')) {
                $transaction->category_source = 'manual';
            }
        });
        static::updated(function (self $transaction): void {
            if ($transaction->category_source === 'manual' && $transaction->wasChanged(['category_group', 'subcategory'])) {
                TransactionCategorizationService::rememberManual($transaction);
            }
        });
    }

    public function bankStatement()
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'id' => $this->id,
            'bank_statement_id' => $this->bank_statement_id,
            'description' => $this->description,
            'counterparty_name' => $this->counterparty_name,
            'reference' => $this->reference,
            'transaction_type' => $this->transaction_type,
            'category_group' => $this->category_group?->value,
            'subcategory' => $this->subcategory,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->deleted_at === null;
    }
}
