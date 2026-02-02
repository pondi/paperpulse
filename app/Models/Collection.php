<?php

namespace App\Models;

use App\Enums\DeletedReason;
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
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'deleted_reason' => DeletedReason::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class)
            ->withTimestamps();
    }

    public function shares(): HasMany
    {
        return $this->hasMany(CollectionShare::class);
    }

    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'collection_shares', 'collection_id', 'shared_with_user_id')
            ->withPivot(['permission', 'shared_at', 'expires_at'])
            ->wherePivot('expires_at', '>', now())
            ->orWherePivotNull('expires_at');
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

    protected static function boot(): void
    {
        parent::boot();

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
