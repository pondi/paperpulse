<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $collection_id
 * @property int $created_by_user_id
 * @property string $token
 * @property string|null $label
 * @property string|null $password_hash
 * @property bool $is_password_protected
 * @property Carbon|null $expires_at
 * @property int|null $max_views
 * @property int $view_count
 * @property bool $is_active
 * @property Carbon|null $last_accessed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection $collection
 * @property-read User $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection|PublicShareAccessLog[] $accessLogs
 */
class PublicCollectionLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_id',
        'created_by_user_id',
        'token',
        'label',
        'password_hash',
        'is_password_protected',
        'expires_at',
        'max_views',
        'view_count',
        'is_active',
        'last_accessed_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_password_protected' => 'boolean',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'max_views' => 'integer',
            'view_count' => 'integer',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(PublicShareAccessLog::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('max_views')
                    ->orWhereColumn('view_count', '<', 'max_views');
            });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_active', false)
                ->orWhere(function (Builder $q2) {
                    $q2->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now());
                })
                ->orWhere(function (Builder $q2) {
                    $q2->whereNotNull('max_views')
                        ->whereColumn('view_count', '>=', 'max_views');
                });
        });
    }

    public function isAccessible(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->hasExpired()) {
            return false;
        }

        if ($this->isViewLimitReached()) {
            return false;
        }

        return true;
    }

    public function hasExpired(): bool
    {
        return ! is_null($this->expires_at) && $this->expires_at->isPast();
    }

    public function isViewLimitReached(): bool
    {
        return ! is_null($this->max_views) && $this->view_count >= $this->max_views;
    }

    public function isPasswordProtected(): bool
    {
        return $this->is_password_protected;
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
        $this->update(['last_accessed_at' => now()]);
    }

    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (static::where('token', $token)->exists());

        return $token;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $link) {
            if (empty($link->token)) {
                $link->token = static::generateToken();
            }
        });
    }
}
