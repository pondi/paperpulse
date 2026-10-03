<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class OrganizationBackfill extends Model
{
    use BelongsToUser;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cursor' => 'integer', 'max_file_id' => 'integer', 'processed' => 'integer', 'skipped' => 'integer',
            'attempts' => 'integer', 'extract_missing' => 'boolean', 'calls' => 'integer', 'tokens' => 'integer',
            'max_calls' => 'integer', 'max_tokens' => 'integer', 'started_at' => 'datetime'];
    }
}
