<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileProcessingRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime', 'dispatched_at' => 'datetime'];
    }
}
