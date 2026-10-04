<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationRecommendation extends Model
{
    use BelongsToUser;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['operation' => 'array', 'before_state' => 'array', 'after_state' => 'array',
            'confidence' => 'float', 'decided_at' => 'datetime', 'undone_at' => 'datetime'];
    }

    /** @return BelongsTo<OrganizationRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(OrganizationRun::class, 'organization_run_id');
    }
}
