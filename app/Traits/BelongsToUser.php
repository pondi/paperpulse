<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    /**
     * Boot the BelongsToUser trait for a model.
     *
     * @return void
     */
    protected static function bootBelongsToUser()
    {
        // Add global scope to filter by authenticated user
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where($builder->getModel()->getTable().'.user_id', auth()->id());
            }
        });

        // Automatically set user_id on creating
        static::creating(function ($model) {
            if (auth()->check() && ! $model->user_id) {
                $model->user_id = auth()->id();
            }
        });
    }

    /**
     * Get the user that owns this model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the owner of this model (alias for user relation).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope a query to include records for a specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int|User  $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $user)
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->withoutGlobalScope('user')->where('user_id', $userId);
    }

    /**
     * Scope a query to include records accessible by a user (owned + shared).
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->withoutGlobalScope('user')->where(function ($q) use ($user) {
            $q->where('user_id', $user->id);

            // If the model uses ShareableModel trait, include shared records
            if (in_array(ShareableModel::class, class_uses_recursive(static::class))) {
                $q->orWhereHas('shares', function ($shareQuery) use ($user) {
                    $shareQuery->where('shared_with_user_id', $user->id)
                        ->where(function ($expQuery) {
                            $expQuery->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                });
            }
        });
    }

    /**
     * Scope a query to include all records (bypass user scope).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithoutUserScope($query)
    {
        return $query->withoutGlobalScope('user');
    }

    /**
     * Check if this model is owned by the given user.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Check if this model is owned by the currently authenticated user.
     */
    public function isOwnedByCurrentUser(): bool
    {
        return auth()->check() && $this->user_id === auth()->id();
    }
}
