<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateFlag extends Model
{
    protected $fillable = [
        'user_id',
        'file_id',
        'duplicate_file_id',
        'reasons',
        'status',
        'resolved_file_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<File, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    /** @return BelongsTo<File, $this> */
    public function duplicateFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'duplicate_file_id');
    }
}
