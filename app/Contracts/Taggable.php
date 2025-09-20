<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Taggable
{
    /**
     * Relation to tags for the model.
     */
    public function tags(): BelongsToMany;
}

