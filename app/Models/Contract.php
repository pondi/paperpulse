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
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Contract extends Model implements Taggable
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
        'contract_number',
        'contract_title',
        'contract_type',
        'parties',
        'effective_date',
        'expiry_date',
        'signature_date',
        'duration',
        'renewal_terms',
        'termination_conditions',
        'contract_value',
        'currency',
        'payment_schedule',
        'governing_law',
        'jurisdiction',
        'status',
        'key_terms',
        'obligations',
        'summary',
        'contract_data',
    ];

    protected $casts = [
        'parties' => 'array',
        'payment_schedule' => 'array',
        'key_terms' => 'array',
        'obligations' => 'array',
        'contract_data' => 'array',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'signature_date' => 'date',
        'contract_value' => 'decimal:2',
        'deleted_reason' => DeletedReason::class,
    ];

    public function getEntityType(): string
    {
        return 'contract';
    }

    protected function getShareableType(): string
    {
        return 'contract';
    }

    protected function getTaggableType(): string
    {
        return 'contract';
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['tags']);

        return [
            'user_id' => $this->user_id,
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'contract_title' => $this->contract_title,
            'contract_type' => $this->contract_type,
            'status' => $this->status,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'contract_value' => $this->contract_value,
            'currency' => $this->currency,
            'tags' => $this->tags?->pluck('name')->toArray() ?? [],
        ];
    }
}
