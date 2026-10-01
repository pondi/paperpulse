<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provides automatic user scoping for multi-tenant Eloquent models.
 *
 * WARNING: The global scope only applies when auth()->check() is true
 * (i.e. during HTTP requests with an authenticated user). In queue workers,
 * console commands, and any other non-HTTP context, the scope is silently
 * disabled and all records are returned regardless of user_id.
 *
 * Always pass user_id explicitly when querying from jobs or services
 * that may run outside of an HTTP request lifecycle.
 */
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
     * @param  Builder  $query
     * @param  int|User  $user
     * @return Builder
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
        $table = $query->getModel()->getTable();

        return $query->withoutGlobalScope('user')->where(function (Builder $query) use ($user, $table): void {
            $query->where($table.'.user_id', $user->id);

            if ($query->getModel() instanceof \App\Models\Collection) {
                $query->orWhereHas('shares', fn (Builder $shares) => $shares
                    ->where('shared_with_user_id', $user->id)->active());
            } elseif ($query->getModel() instanceof \App\Models\File) {
                $query->orWhereHas('shares', fn (Builder $shares) => $shares
                    ->where('shared_with_user_id', $user->id)->active())
                    ->orWhereHas('collections', fn (Builder $collections) => $collections
                        ->withoutGlobalScope('user')
                        ->whereHas('shares', fn (Builder $shares) => $shares
                            ->where('shared_with_user_id', $user->id)->active()));
            } elseif (method_exists($query->getModel(), 'file')) {
                $query->orWhereHas('file', fn (Builder $files) => $files->accessibleBy($user));
            }
        });
    }

    /**
     * Scope a query to include all records (bypass user scope).
     *
     * @param  Builder  $query
     * @return Builder
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
