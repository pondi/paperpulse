<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PulseDavImportBatch extends Model
{
    use HasFactory;

    protected $table = 'pulsedav_import_batches';

    protected $fillable = [
        'user_id',
        'imported_at',
        'file_count',
        'tag_ids',
        'notes',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
        'tag_ids' => 'array',
        'file_count' => 'integer',
    ];

    /**
     * Get the user that owns the import batch.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the files in this import batch.
     */
    public function files()
    {
        return $this->hasMany(PulseDavFile::class, 'import_batch_id');
    }

    /**
     * Get the tags for this batch
     */
    public function getTagsAttribute()
    {
        if (!$this->tag_ids) {
            return collect();
        }
        
        return Tag::whereIn('id', $this->tag_ids)->get();
    }

    /**
     * Scope to batches for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}