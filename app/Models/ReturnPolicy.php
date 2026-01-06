<?php

namespace App\Models;

use App\Contracts\Taggable;
use App\Traits\BelongsToUser;
use App\Traits\ExtractableEntity as ExtractableEntityTrait;
use App\Traits\ShareableModel;
use App\Traits\TaggableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class ReturnPolicy extends Model implements Taggable
{
    use BelongsToUser;
    use ExtractableEntityTrait;
    use HasFactory;
    use Searchable;
    use ShareableModel;
    use TaggableModel;

    protected $fillable = [
        'file_id',
        'user_id',
        'receipt_id',
        'invoice_id',
        'merchant_id',
        'return_deadline',
        'exchange_deadline',
        'conditions',
        'refund_method',
        'restocking_fee',
        'restocking_fee_percentage',
        'is_final_sale',
        'requires_receipt',
        'requires_original_packaging',
        'policy_data',
    ];

    protected $casts = [
        'policy_data' => 'array',
        'return_deadline' => 'date',
        'exchange_deadline' => 'date',
        'is_final_sale' => 'boolean',
        'requires_receipt' => 'boolean',
        'requires_original_packaging' => 'boolean',
        'restocking_fee' => 'decimal:2',
        'restocking_fee_percentage' => 'decimal:2',
    ];

    public function receipt(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function merchant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function getEntityType(): string
    {
        return 'return_policy';
    }

    protected function getShareableType(): string
    {
        return 'return_policy';
    }

    protected function getTaggableType(): string
    {
        return 'return_policy';
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['merchant', 'tags']);

        return [
            'user_id' => $this->user_id,
            'id' => $this->id,
            'merchant_name' => $this->merchant?->name,
            'return_deadline' => $this->return_deadline?->format('Y-m-d'),
            'exchange_deadline' => $this->exchange_deadline?->format('Y-m-d'),
            'refund_method' => $this->refund_method,
            'is_final_sale' => $this->is_final_sale,
            'requires_receipt' => $this->requires_receipt,
            'requires_original_packaging' => $this->requires_original_packaging,
            'tags' => $this->tags?->pluck('name')->toArray() ?? [],
        ];
    }
}
