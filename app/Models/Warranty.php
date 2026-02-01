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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $warranty_end_date
 * @property-read User $user
 * @property-read File|null $file
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 */
class Warranty extends Model implements Taggable
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
        'receipt_id',
        'invoice_id',
        'product_name',
        'product_category',
        'manufacturer',
        'model_number',
        'serial_number',
        'purchase_date',
        'warranty_start_date',
        'warranty_end_date',
        'warranty_duration',
        'warranty_type',
        'warranty_provider',
        'warranty_number',
        'coverage_type',
        'coverage_description',
        'exclusions',
        'support_phone',
        'support_email',
        'support_website',
        'warranty_data',
    ];

    protected $casts = [
        'warranty_data' => 'array',
        'purchase_date' => 'date',
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
        'deleted_reason' => DeletedReason::class,
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getEntityType(): string
    {
        return 'warranty';
    }

    protected function getShareableType(): string
    {
        return 'warranty';
    }

    protected function getTaggableType(): string
    {
        return 'warranty';
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['tags']);

        return [
            'user_id' => $this->user_id,
            'id' => $this->id,
            'product_name' => $this->product_name,
            'product_category' => $this->product_category,
            'manufacturer' => $this->manufacturer,
            'warranty_type' => $this->warranty_type,
            'warranty_end_date' => $this->warranty_end_date?->format('Y-m-d'),
            'receipt_id' => $this->receipt_id,
            'invoice_id' => $this->invoice_id,
            'tags' => $this->tags?->pluck('name')->toArray() ?? [],
        ];
    }
}
