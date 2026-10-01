<?php

namespace App\Models;

use App\Enums\TransactionCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankCategorizationRule extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'fingerprint', 'category_group', 'subcategory', 'source'];

    protected function casts(): array
    {
        return ['category_group' => TransactionCategory::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
