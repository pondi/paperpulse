<?php

namespace App\Models;

use App\Enums\DeletedReason;
use App\Traits\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
 * @property Carbon|null $uploaded_at
 * @property Carbon|null $file_created_at
 * @property Carbon|null $file_modified_at
 * @property-read \Illuminate\Database\Eloquent\Collection|Collection[] $collections
 * @property-read ExtractableEntity|null $primaryEntity
 */
class File extends Model
{
    use BelongsToUser;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'size',
        'data',
        'uploaded_at',
        'file_created_at',
        'file_modified_at',
        'file_path',
        'original_filename',
        'file_size',
        'mime_type',
        'status',
        'note',
        'meta',
        'fileName',
        'fileExtension',
        'fileType',
        'fileSize',
        'guid',
        'file_hash',
        'file_type',
        's3_original_path',
        's3_processed_path',
        's3_archive_path',
        's3_image_path',
        'has_image_preview',
        'image_generation_error',
        'processing_type',
    ];

    protected $casts = [
        'meta' => 'array',
        'has_image_preview' => 'boolean',
        'uploaded_at' => 'datetime',
        'file_created_at' => 'datetime',
        'file_modified_at' => 'datetime',
        'deleted_reason' => DeletedReason::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function extractableEntities()
    {
        return $this->hasMany(ExtractableEntity::class);
    }

    public function primaryEntity()
    {
        return $this->hasOne(ExtractableEntity::class)
            ->whereRaw('is_primary = true')
            ->with('entity');
    }

    public function conversion()
    {
        return $this->hasOne(FileConversion::class);
    }

    public function shares()
    {
        return $this->hasMany(FileShare::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)
            ->withTimestamps();
    }

    /**
     * Get the tags for this file.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'file_tags')
            ->withTimestamps();
    }

    /**
     * Add a tag to this file.
     */
    public function addTag(Tag $tag): void
    {
        $this->tags()->syncWithoutDetaching([$tag->id]);
    }

    /**
     * Add a tag by name to this file.
     */
    public function addTagByName(string $name): Tag
    {
        $tag = Tag::findOrCreateByName($name, $this->user_id);
        $this->addTag($tag);

        return $tag;
    }

    /**
     * Remove a tag from this file.
     */
    public function removeTag(Tag $tag): void
    {
        $this->tags()->detach($tag->id);
    }

    /**
     * Sync tags for this file.
     */
    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
    }

    /**
     * Check if this file has a specific tag.
     */
    public function hasTag(Tag $tag): bool
    {
        return $this->tags()->where('tags.id', $tag->id)->exists();
    }

    /**
     * Get tag names as an array.
     */
    public function getTagNames(): array
    {
        return $this->tags()->pluck('name')->toArray();
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
     * @return FileShare|null
     */
    public function getShareFor($userId)
    {
        return $this->shares()
            ->active()
            ->where('shared_with_user_id', $userId)
            ->first();
    }

    /**
     * Accessor for lowercase 'filename' to map to camelCase 'fileName' column.
     * Provides backwards compatibility and developer convenience.
     */
    public function getFilenameAttribute(): ?string
    {
        return $this->attributes['fileName'] ?? null;
    }
}
