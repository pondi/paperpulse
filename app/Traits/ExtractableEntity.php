<?php

namespace App\Traits;

use App\Models\ExtractableEntity as ExtractableEntityModel;
use App\Models\File;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait ExtractableEntity
{
    /**
     * Polymorphic relation to the extraction junction record.
     */
    public function extraction(): MorphOne
    {
        return $this->morphOne(ExtractableEntityModel::class, 'entity', 'entity_type', 'entity_id');
    }

    /**
     * Get the file this entity was extracted from.
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Define the entity type for the junction table.
     */
    abstract public function getEntityType(): string;

    /**
     * Ensure morph type uses the short entity type identifier.
     */
    public function getMorphClass()
    {
        return $this->getEntityType();
    }
}
