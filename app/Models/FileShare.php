<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * FileShare Model
 *
 * @property int $id
 * @property int $file_id
 * @property int $user_id
 * @property int $shared_with_user_id
 * @property string $permission
 * @property string|null $share_token
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $accessed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \App\Models\File $file
 * @property-read \App\Models\User $user
 * @property-read \App\Models\User $sharedWithUser
 */
class FileShare extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'file_id',
        'user_id',
        'shared_with_user_id',
        'permission',
        'share_token',
        'expires_at',
        'accessed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'accessed_at' => 'datetime',
    ];

    /**
     * Permission constants.
     */
    const PERMISSION_VIEW = 'view';
    const PERMISSION_EDIT = 'edit';

    /**
     * Get the file that is being shared.
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Get the user who created the share.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user the file is shared with.
     */
    public function sharedWithUser()
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    /**
     * Scope a query to only include active shares.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope a query to only include expired shares.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope a query to filter by permission.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $permission
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithPermission($query, $permission)
    {
        return $query->where('permission', $permission);
    }

    /**
     * Scope a query to filter shares for a specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSharedWith($query, $userId)
    {
        return $query->where('shared_with_user_id', $userId);
    }

    /**
     * Check if the share is active.
     *
     * @return bool
     */
    public function isActive()
    {
        return is_null($this->expires_at) || $this->expires_at->isFuture();
    }

    /**
     * Check if the share has expired.
     *
     * @return bool
     */
    public function hasExpired()
    {
        return !is_null($this->expires_at) && $this->expires_at->isPast();
    }

    /**
     * Check if the share allows viewing.
     *
     * @return bool
     */
    public function canView()
    {
        return $this->isActive() && in_array($this->permission, [self::PERMISSION_VIEW, self::PERMISSION_EDIT]);
    }

    /**
     * Check if the share allows editing.
     *
     * @return bool
     */
    public function canEdit()
    {
        return $this->isActive() && $this->permission === self::PERMISSION_EDIT;
    }

    /**
     * Mark the share as accessed.
     *
     * @return void
     */
    public function markAsAccessed()
    {
        $this->update(['accessed_at' => now()]);
    }

    /**
     * Generate a unique share token.
     *
     * @return string
     */
    public static function generateShareToken()
    {
        do {
            $token = Str::random(32);
        } while (static::where('share_token', $token)->exists());

        return $token;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Generate share token when creating
        static::creating(function ($share) {
            if (empty($share->share_token)) {
                $share->share_token = static::generateShareToken();
            }
        });
    }

    /**
     * Get all available permissions.
     *
     * @return array
     */
    public static function getPermissions()
    {
        return [
            self::PERMISSION_VIEW => 'View',
            self::PERMISSION_EDIT => 'Edit',
        ];
    }
}