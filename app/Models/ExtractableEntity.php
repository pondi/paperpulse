<?php

namespace App\Models;

use App\Enums\DeletedReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property-read Receipt|Document|Invoice|Contract|BankStatement|Voucher|Warranty|ReturnPolicy|null $entity */
class ExtractableEntity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'file_id',
        'user_id',
        'entity_type',
        'entity_id',
        'is_primary',
        'confidence_score',
        'extraction_provider',
        'extraction_model',
        'extraction_metadata',
        'extracted_at',
    ];

    protected $casts = [
        'extraction_metadata' => 'array',
        'extracted_at' => 'datetime',
        'is_primary' => 'boolean',
        'deleted_reason' => DeletedReason::class,
    ];

    /** @return BelongsTo<File, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the owning entity (polymorphic).
     *
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }
}
