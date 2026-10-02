<?php

namespace App\Models;

use App\Contracts\Taggable;
use App\Enums\DeletedReason;
use App\Traits\BelongsToUser;
use App\Traits\ExtractableEntity as ExtractableEntityTrait;
use App\Traits\InvalidatesSearchFacets;
use App\Traits\ShareableModel;
use App\Traits\TaggableModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
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
 * @property-read Collection<int, Tag> $tags
 */
class Voucher extends Model implements Taggable
{
    use BelongsToUser;
    use ExtractableEntityTrait;
    use HasFactory;
    use InvalidatesSearchFacets;
    use Searchable;
    use ShareableModel;
    use SoftDeletes;
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
        'expiry_date' => 'date:Y-m-d',
        'first_payment_date' => 'date',
        'final_payment_date' => 'date',
        'redeemed_at' => 'datetime',
        'is_redeemed' => 'boolean',
        'original_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'deleted_reason' => DeletedReason::class,
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
        return $this->expiry_date !== null
            && $this->expiry_date->toDateString() < $this->user->currentDate()->toDateString();
    }

    public function isExpiringSoon(): bool
    {
        if ($this->expiry_date === null || $this->is_redeemed) {
            return false;
        }

        $today = $this->user->currentDate();
        $expiryDate = $this->expiry_date->toDateString();

        return $expiryDate >= $today->toDateString()
            && $expiryDate <= $today->addDays(30)->toDateString();
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing('file.collections');

        $this->loadMissing(['merchant', 'tags']);

        return [
            'user_id' => $this->user_id,
            'id' => $this->id,
            'collection_ids' => $this->file?->collections->modelKeys() ?? [],
            'voucher_type' => $this->voucher_type,
            'code' => $this->code,
            'merchant_name' => $this->merchant?->name,
            'expiry_date' => $this->expiry_date ? (int) $this->expiry_date->format('Ymd') : null,
            'original_value' => $this->original_value === null ? null : (float) $this->original_value,
            'current_value' => $this->current_value === null ? null : (float) $this->current_value,
            'currency' => $this->currency,
            'is_redeemed' => $this->is_redeemed,
            'tags' => $this->tags?->pluck('name')->toArray() ?? [],
        ];
    }
}
