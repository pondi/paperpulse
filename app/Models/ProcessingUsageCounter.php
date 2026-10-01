<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingUsageCounter extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'scope_key', 'usage', 'expires_at'];

    protected function casts(): array
    {
        return ['usage' => 'array', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
