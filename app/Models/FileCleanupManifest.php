<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileCleanupManifest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'objects' => 'array',
            'search_records' => 'array',
            'available_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
