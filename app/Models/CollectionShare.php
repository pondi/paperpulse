<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * CollectionShare Model
 *
 * @property int $id
 * @property int $collection_id
 * @property int $shared_by_user_id
 * @property int $shared_with_user_id
 * @property string $permission
 * @property string|null $share_token
 * @property Carbon $shared_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $accessed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection $collection
 * @property-read User $sharedBy
 * @property-read User $sharedWithUser
 */
class CollectionShare extends Model
{
    use HasFactory;

    public const PERMISSION_VIEW = 'view';

    public const PERMISSION_EDIT = 'edit';

    protected $fillable = [
        'collection_id',
        'shared_by_user_id',
        'shared_with_user_id',
        'permission',
        'share_token',
        'shared_at',
        'expires_at',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'shared_at' => 'datetime',
            'expires_at' => 'datetime',
            'accessed_at' => 'datetime',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }

    public function sharedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeWithPermission(Builder $query, string $permission): Builder
    {
        return $query->where('permission', $permission);
    }

    public function scopeSharedWith(Builder $query, int $userId): Builder
    {
        return $query->where('shared_with_user_id', $userId);
    }

    public function isActive(): bool
    {
        return is_null($this->expires_at) || $this->expires_at->isFuture();
    }

    public function hasExpired(): bool
    {
        return ! is_null($this->expires_at) && $this->expires_at->isPast();
    }

    public function canView(): bool
    {
        return $this->isActive() && in_array($this->permission, [self::PERMISSION_VIEW, self::PERMISSION_EDIT]);
    }

    public function canEdit(): bool
    {
        return $this->isActive() && $this->permission === self::PERMISSION_EDIT;
    }

    public function markAsAccessed(): void
    {
        $this->update(['accessed_at' => now()]);
    }

    public static function generateShareToken(): string
    {
        do {
            $token = Str::random(32);
        } while (static::where('share_token', $token)->exists());

        return $token;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($share) {
            if (empty($share->share_token)) {
                $share->share_token = static::generateShareToken();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            self::PERMISSION_VIEW => 'View',
            self::PERMISSION_EDIT => 'Edit',
        ];
    }
}
