<?php

namespace App\Models;

use App\Enums\DeletedReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'bank_statement_id',
        'transaction_date',
        'posting_date',
        'description',
        'reference',
        'transaction_type',
        'category',
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
        'deleted_reason' => DeletedReason::class,
    ];

    public function bankStatement()
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }
}
