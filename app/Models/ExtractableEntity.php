<?php

namespace App\Models;

use App\Enums\DeletedReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the owning entity (polymorphic).
     */
    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }
}
