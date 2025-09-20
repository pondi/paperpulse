<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * App\\Models\\File
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $original_filename
 * @property string|null $file_path
 * @property string|null $mime_type
 * @property string|null $status
 * @property string|null $guid
 * @property int|null $file_size
 * @property bool|null $has_image_preview
 * @property \Carbon\Carbon|null $uploaded_at
 */
class File extends Model
{
    use BelongsToUser;
    use HasFactory;
    // use Searchable; // Temporarily disabled due to Meilisearch configuration issue

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'size',
        'data',
        'uploaded_at',
        'file_path',
        'original_filename',
        'file_size',
        'mime_type',
        'status',
        'meta',
        'fileName',
        'fileExtension',
        'fileType',
        'fileSize',
        'guid',
        'file_type',
        's3_original_path',
        's3_processed_path',
        's3_image_path',
        'has_image_preview',
        'image_generation_error',
        'processing_type',
    ];

    protected $casts = [
        'meta' => 'array',
        'uploaded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function shares()
    {
        return $this->hasMany(FileShare::class);
    }

    /**
     * Check if the file is shared with a specific user.
     *
     * @param  int  $userId
     * @return bool
     */
    public function isSharedWith($userId)
    {
        return $this->shares()
            ->active()
            ->where('shared_with_user_id', $userId)
            ->exists();
    }

    /**
     * Get the active share for a specific user.
     *
     * @param  int  $userId
     * @return \App\Models\FileShare|null
     */
    public function getShareFor($userId)
    {
        return $this->shares()
            ->active()
            ->where('shared_with_user_id', $userId)
            ->first();
    }
}
