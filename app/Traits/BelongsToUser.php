<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

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
                $builder->where((new static)->getTable().'.user_id', auth()->id());
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
     * Scope a query to include records for a specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $userId)
    {
        return $query->withoutGlobalScope('user')->where('user_id', $userId);
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
}
