<?php

namespace App\Models;

use Database\Factories\ArchiveExportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveExport extends Model
{
    /** @use HasFactory<ArchiveExportFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'format', 'status', 'filters', 'total', 'processed', 'path', 'error', 'expires_at'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
