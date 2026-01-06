<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicateFlag extends Model
{
    protected $fillable = [
        'user_id',
        'file_id',
        'duplicate_file_id',
        'reason',
        'status',
        'resolved_file_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function file(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function duplicateFile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(File::class, 'duplicate_file_id');
    }
}
