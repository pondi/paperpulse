<?php

namespace App\Models;

use App\Contracts\Taggable;
use App\Traits\BelongsToUser;
use App\Traits\ExtractableEntity as ExtractableEntityTrait;
use App\Traits\ShareableModel;
use App\Traits\TaggableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $merchant_id
 * @property Carbon|null $expiry_date
 * @property bool $is_redeemed
 * @property-read User $user
 * @property-read Merchant|null $merchant
 * @property-read File|null $file
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 */
class Voucher extends Model implements Taggable
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
        'merchant_id',
        'voucher_type',
        'code',
        'barcode',
        'qr_code',
        'issue_date',
        'expiry_date',
        'original_value',
        'current_value',
        'currency',
        'installment_count',
        'monthly_payment',
        'first_payment_date',
        'final_payment_date',
        'is_redeemed',
        'redeemed_at',
        'redemption_location',
        'terms_and_conditions',
        'restrictions',
        'voucher_data',
    ];

    protected $casts = [
        'voucher_data' => 'array',
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'first_payment_date' => 'date',
        'final_payment_date' => 'date',
        'redeemed_at' => 'datetime',
        'is_redeemed' => 'boolean',
        'original_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function getEntityType(): string
    {
        return 'voucher';
    }

    protected function getShareableType(): string
    {
        return 'voucher';
    }

    protected function getTaggableType(): string
    {
        return 'voucher';
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(): bool
    {
        return $this->expiry_date
            && $this->expiry_date->isFuture()
            && $this->expiry_date->diffInDays() <= 30;
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['merchant', 'tags']);

        return [
            'user_id' => $this->user_id,
            'id' => $this->id,
            'voucher_type' => $this->voucher_type,
            'code' => $this->code,
            'merchant_name' => $this->merchant?->name,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'original_value' => $this->original_value,
            'current_value' => $this->current_value,
            'currency' => $this->currency,
            'is_redeemed' => $this->is_redeemed,
            'is_expired' => $this->isExpired(),
            'tags' => $this->tags?->pluck('name')->toArray() ?? [],
        ];
    }
}
