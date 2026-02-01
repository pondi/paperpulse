<?php

namespace App\Models;

use App\Contracts\Taggable;
use App\Enums\DeletedReason;
use App\Traits\BelongsToUser;
use App\Traits\ExtractableEntity as ExtractableEntityTrait;
use App\Traits\ShareableModel;
use App\Traits\TaggableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class BankStatement extends Model implements Taggable
{
    use BelongsToUser;
    use ExtractableEntityTrait;
    use HasFactory;
    use Searchable;
    use ShareableModel;
    use SoftDeletes;
    use TaggableModel;

    protected $fillable = [
        'file_id',
        'user_id',
        'bank_name',
        'account_holder_name',
        'account_number',
        'iban',
        'swift_code',
        'statement_date',
        'statement_period_start',
        'statement_period_end',
        'opening_balance',
        'closing_balance',
        'currency',
        'total_credits',
        'total_debits',
        'transaction_count',
        'statement_data',
    ];

    protected $casts = [
        'statement_data' => 'array',
        'statement_date' => 'date',
        'statement_period_start' => 'date',
        'statement_period_end' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'total_credits' => 'decimal:2',
        'total_debits' => 'decimal:2',
        'deleted_reason' => DeletedReason::class,
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function getEntityType(): string
    {
        return 'bank_statement';
    }

    protected function getShareableType(): string
    {
        return 'bank_statement';
    }

    protected function getTaggableType(): string
    {
        return 'bank_statement';
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['tags']);

        return [
            'user_id' => $this->user_id,
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'account_holder_name' => $this->account_holder_name,
            'account_number' => $this->account_number,
            'statement_date' => $this->statement_date?->format('Y-m-d'),
            'statement_period_start' => $this->statement_period_start?->format('Y-m-d'),
            'statement_period_end' => $this->statement_period_end?->format('Y-m-d'),
            'opening_balance' => $this->opening_balance,
            'closing_balance' => $this->closing_balance,
            'currency' => $this->currency,
            'transaction_count' => $this->transaction_count,
            'tags' => $this->tags?->pluck('name')->toArray() ?? [],
        ];
    }
}
