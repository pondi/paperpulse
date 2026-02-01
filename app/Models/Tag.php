<?php

namespace App\Models;

use App\Enums\DeletedReason;
use App\Traits\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Tag Model
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Collection|File[] $files
 * @property-read int $usage_count
 */
class Tag extends Model
{
    use BelongsToUser;
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'color',
    ];

    protected $casts = [
        'deleted_reason' => DeletedReason::class,
    ];

    /**
     * Get the user that owns the tag.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the files that have this tag.
     */
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'file_tags')
            ->withTimestamps();
    }

    /**
     * Get the total usage count for this tag.
     *
     * @return int
     */
    public function getUsageCountAttribute()
    {
        return $this->files()->count();
    }

    /**
     * Scope a query to search tags by name.
     *
     * @param  Builder  $query
     * @param  string  $search
     * @return Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }

    /**
     * Scope a query to order by usage count.
     *
     * @param  Builder  $query
     * @param  string  $direction
     * @return Builder
     */
    public function scopeOrderByUsage($query, $direction = 'desc')
    {
        return $query->withCount('files')
            ->orderBy('files_count', $direction);
    }

    /**
     * Generate a unique slug for the tag.
     *
     * @param  string  $name
     * @param  int  $userId
     * @param  int|null  $excludeId
     * @return string
     */
    public static function generateUniqueSlug($name, $userId, $excludeId = null)
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

    /**
     * Find or create a tag by name for a user.
     *
     * @param  string  $name
     * @param  int  $userId
     * @param  string|null  $color
     * @return static
     */
    public static function findOrCreateByName($name, $userId, $color = null)
    {
        $tag = static::where('user_id', $userId)
            ->where('name', $name)
            ->first();

        if (! $tag) {
            $tag = static::create([
                'user_id' => $userId,
                'name' => $name,
                'slug' => static::generateUniqueSlug($name, $userId),
                'color' => $color ?? static::generateRandomColor(),
            ]);
        }

        return $tag;
    }

    /**
     * Generate a random color for the tag.
     *
     * @return string
     */
    protected static function generateRandomColor()
    {
        $colors = [
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

        return $colors[array_rand($colors)];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Generate slug when creating
        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = static::generateUniqueSlug($tag->name, $tag->user_id);
            }

            if (empty($tag->color)) {
                $tag->color = static::generateRandomColor();
            }
        });

        // Update slug when updating
        static::updating(function ($tag) {
            if ($tag->isDirty('name')) {
                $tag->slug = static::generateUniqueSlug($tag->name, $tag->user_id, $tag->id);
            }
        });
    }
}
