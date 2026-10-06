<?php

namespace App\Models;

use App\Enums\DeletedReason;
use App\Jobs\Search\ReindexFile;
use App\Services\Files\FileProcessingFailureReporter;
use App\Traits\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\\Models\\File
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $fileName
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

    protected static function booted(): void
    {
        static::updated(function (self $file): void {
            if ($file->wasChanged('status') && $file->status === 'needs_review') {
                $reason = $file->meta['review']['reason'] ?? 'unknown';
                app(FileProcessingFailureReporter::class)->report(
                    'File processing requires review: '.$reason,
                    'review:'.$reason,
                    $file,
                    ['reason' => $reason, 'review' => $file->meta['review'] ?? [], 'coverage' => $file->meta['processing_coverage'] ?? []],
                );
            }
            if ($file->wasChanged('note')) {
                ReindexFile::forFile($file->id);
            }
        });
    }

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
        'primary_folder_id',
        'placement_source',
        'placement_version',
        'organization_summary',
    ];

    protected $casts = [
        'meta' => 'array',
        'placement_version' => 'integer',
        'organization_summary' => 'array',
        'has_image_preview' => 'boolean',
        'uploaded_at' => 'datetime',
        'file_created_at' => 'datetime',
        'file_modified_at' => 'datetime',
        'deleted_reason' => DeletedReason::class,
    ];

    /** @return HasMany<JobHistory, $this> */
    public function processingJobs(): HasMany
    {
        return $this->hasMany(JobHistory::class)->whereNull('parent_uuid');
    }

    public function processingSummary(): ?array
    {
        $job = $this->processingJobs->first();
        if ($job === null) {
            return null;
        }
        $tasks = $job->tasks->sortByDesc('updated_at');
        $task = $tasks->firstWhere('status', 'processing') ?? $tasks->firstWhere('status', 'retrying')
            ?? $tasks->firstWhere('status', 'failed') ?? $tasks->first();
        $startedAt = $job->started_at ?? $job->tasks->whereNotNull('started_at')->min('started_at');
        $finishedAt = $job->finished_at;
        $active = in_array($this->status, ['pending', 'processing'], true);

        return ['state' => $active ? ($task?->status ?? $job->status) : $this->status,
            'stage' => $task?->name, 'queued_at' => $job->created_at?->toIso8601String(),
            'started_at' => $startedAt?->toIso8601String(), 'finished_at' => $finishedAt?->toIso8601String(),
            'elapsed_seconds' => $startedAt && ($active || $finishedAt) ? (int) $startedAt->diffInSeconds($finishedAt ?? now()) : null,
            'attempt' => $task?->attempt, 'progress' => $task?->progress ?? $job->progress];
    }

    public function scopeRetainable(Builder $query, Carbon $cutoff): Builder
    {
        return $query->where('status', 'completed')
            ->whereHas('processingJobs', fn (Builder $jobs) => $jobs
                ->where('status', 'completed')->where('finished_at', '<', $cutoff))
            ->whereDoesntHave('processingJobs', fn (Builder $jobs) => $jobs
                ->where(function (Builder $jobs) use ($cutoff): void {
                    $jobs->whereNotIn('status', ['completed', 'failed', 'cancelled'])
                        ->orWhere(function (Builder $jobs) use ($cutoff): void {
                            $jobs->where('status', 'completed')->where('finished_at', '>=', $cutoff);
                        });
                }));
    }

    public function scopeDeduplicatable(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereIn('status', ['pending', 'processing', 'needs_review'])
                ->orWhere(function (Builder $query): void {
                    $query->where('status', 'completed')->whereHas('extractableEntities');
                });
        });
    }

    /** @return BelongsTo<Collection, $this> */
    public function primaryFolder(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'primary_folder_id');
    }

    /** @return HasMany<Receipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ExtractableEntity, $this> */
    public function extractableEntities(): HasMany
    {
        return $this->hasMany(ExtractableEntity::class);
    }

    /** @return HasOne<ExtractableEntity, $this> */
    public function primaryEntity(): HasOne
    {
        return $this->hasOne(ExtractableEntity::class)
            ->where('is_primary', true)
            ->with('entity');
    }

    /** @return HasOne<FileConversion, $this> */
    public function conversion(): HasOne
    {
        return $this->hasOne(FileConversion::class);
    }

    /** @return HasMany<FileShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(FileShare::class);
    }

    /** @return BelongsToMany<Collection, $this, SearchableFilePivot> */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)
            ->using(SearchableFilePivot::class)
            ->withTimestamps();
    }

    /**
     * Get the tags for this file.
     *
     * @return BelongsToMany<Tag, $this, SearchableFilePivot>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'file_tags')
            ->using(SearchableFilePivot::class)
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
