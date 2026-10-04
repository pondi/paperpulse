<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationRun extends Model
{
    use BelongsToUser;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['input_revision' => 'integer', 'attempts' => 'integer', 'cursor' => 'integer',
            'calls' => 'integer', 'tokens' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return HasMany<OrganizationRecommendation, $this> */
    public function recommendations(): HasMany
    {
        return $this->hasMany(OrganizationRecommendation::class);
    }
}
