<?php

namespace App\Models;

use App\Enums\DeletedReason;
use App\Jobs\Search\ReindexFile;
use App\Traits\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Collection Model
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $icon
 * @property string $color
 * @property bool $is_archived
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read EloquentCollection|File[] $files
 * @property-read EloquentCollection|CollectionShare[] $shares
 * @property-read int $files_count
 */
class Collection extends Model
{
    use BelongsToUser;
    use HasFactory;
    use SoftDeletes;

    public const ICONS = [
        'folder',
        'folder-open',
        'document',
        'document-text',
        'receipt-refund',
        'briefcase',
        'shopping-bag',
        'home',
        'heart',
        'star',
        'tag',
        'archive-box',
        'building-office',
        'credit-card',
        'currency-dollar',
        'calendar',
        'clipboard',
        'cog',
        'cube',
        'gift',
        'key',
        'truck',
        'wrench',
        'camera',
        'book-open',
    ];

    public const COLORS = [
        '#EF4444', // red
        '#F59E0B', // amber
        '#10B981', // emerald
        '#3B82F6', // blue
        '#6366F1', // indigo
        '#8B5CF6', // violet
        '#EC4899', // pink
        '#14B8A6', // teal
        '#F97316', // orange
        '#84CC16', // lime
    ];

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'is_archived',
        'parent_id',
        'folder_type',
        'organization_source',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'is_pinned' => 'boolean',
            'deleted_reason' => DeletedReason::class,
        ];
    }

    /** @return BelongsTo<Collection, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Collection, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<File, $this> */
    public function primaryFiles(): HasMany
    {
        return $this->hasMany(File::class, 'primary_folder_id');
    }

    public static function normalizeIdentity(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    public static function folderIdentity(string $name, ?int $parentId, string $type): string
    {
        return hash('sha256', ($parentId ?? 0).'|'.$type.'|'.static::normalizeIdentity($name));
    }

    public function validateTreePosition(): void
    {
        $visited = $this->exists ? [$this->id] : [];
        $parentId = $this->parent_id;
        for ($depth = 0; $parentId !== null; $depth++) {
            if ($depth >= 64 || in_array($parentId, $visited, true)) {
                throw ValidationException::withMessages(['parent_id' => 'Folder trees cannot contain cycles or exceed 64 levels.']);
            }
            $parent = static::withoutGlobalScope('user')->where('user_id', $this->user_id)->find($parentId);
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => 'Select an active folder you own.']);
            }
            $visited[] = $parentId;
            $parentId = $parent->parent_id;
        }
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<File, $this, SearchableFilePivot> */
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class)
            ->using(SearchableFilePivot::class)
            ->withPivot('is_primary_placement')->withTimestamps();
    }

    /** @return HasMany<CollectionShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(CollectionShare::class);
    }

    /** @return HasMany<PublicCollectionLink, $this> */
    public function publicLinks(): HasMany
    {
        return $this->hasMany(PublicCollectionLink::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'collection_shares', 'collection_id', 'shared_with_user_id')
            ->withPivot(['permission', 'shared_at', 'expires_at'])
            ->where(function (Builder $query) {
                $query->whereNull('collection_shares.expires_at')
                    ->orWhere('collection_shares.expires_at', '>', now());
            });
    }

    public function getFilesCountAttribute(): int
    {
        return $this->files()->count();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        $searchLower = strtolower($search);

        return $query->where(function ($q) use ($searchLower) {
            $q->whereRaw('LOWER(name) LIKE ?', ['%'.$searchLower.'%'])
                ->orWhereRaw('LOWER(description) LIKE ?', ['%'.$searchLower.'%']);
        });
    }

    public function scopeOrderByFileCount(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->withCount('files')
            ->orderBy('files_count', $direction);
    }

    public static function generateUniqueSlug(string $name, int $userId, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (true) {
            $query = static::where('user_id', $userId)->where('slug', $slug);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (! $query->exists()) {
                break;
            }

            $slug = $originalSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    public static function findOrCreateByName(string $name, int $userId, ?string $icon = null, ?string $color = null): self
    {
        /** @var self|null $collection */
        $collection = static::where('user_id', $userId)
            ->where('name', $name)
            ->first();

        if (! $collection) {
            /** @var self $collection */
            $collection = static::create([
                'user_id' => $userId,
                'name' => $name,
                'slug' => static::generateUniqueSlug($name, $userId),
                'icon' => $icon ?? 'folder',
                'color' => $color ?? static::generateRandomColor(),
            ]);
        }

        return $collection;
    }

    protected static function generateRandomColor(): string
    {
        return static::COLORS[array_rand(static::COLORS)];
    }

    public function isSharedWith(User $user): bool
    {
        return $this->shares()
            ->where('shared_with_user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function canBeViewedBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->isSharedWith($user);
    }

    public function canBeEditedBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->shares()
            ->where('shared_with_user_id', $user->id)
            ->where('permission', 'edit')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    protected static function booted(): void
    {
        $reindex = function (self $model): void {
            foreach ($model->files()->withoutGlobalScope('user')->where('files.user_id', $model->user_id)->cursor() as $file) {
                ReindexFile::forFile($file->id);
            }
        };
        static::updated(function (self $model) use ($reindex): void {
            if ($model->wasChanged('name')) {
                $reindex($model);
            }
        });
        static::deleted($reindex);
        static::restored($reindex);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $collection): void {
            $collection->validateTreePosition();
            if (! $collection->exists || $collection->isDirty(['name', 'parent_id', 'folder_type'])) {
                $collection->folder_type ??= 'folder';
                $collection->normalized_name = static::normalizeIdentity($collection->name);
                $collection->identity_key = static::folderIdentity($collection->name, $collection->parent_id, $collection->folder_type);
            }
        });

        static::creating(function ($collection) {
            if (empty($collection->slug)) {
                $collection->slug = static::generateUniqueSlug($collection->name, $collection->user_id);
            }

            if (empty($collection->color)) {
                $collection->color = static::generateRandomColor();
            }

            if (empty($collection->icon)) {
                $collection->icon = 'folder';
            }
        });

        static::updating(function ($collection) {
            if ($collection->isDirty('name')) {
                $collection->slug = static::generateUniqueSlug($collection->name, $collection->user_id, $collection->id);
            }
        });
    }
}
