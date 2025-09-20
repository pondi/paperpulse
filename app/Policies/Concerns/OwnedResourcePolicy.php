<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner-only access policy for simple resources.
 * View/create is allowed broadly; mutations require ownership.
 */
trait OwnedResourcePolicy
{
    /**
     * Allow listing; queries should be scoped per-user elsewhere.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Allow viewing only if owner.
     */
    public function view(User $user, Model $model): bool
    {
        return $user->id === $model->user_id;
    }

    /**
     * Allow creating.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Allow updates only by owner.
     */
    public function update(User $user, Model $model): bool
    {
        return $user->id === $model->user_id;
    }

    /**
     * Allow deletion only by owner.
     */
    public function delete(User $user, Model $model): bool
    {
        return $user->id === $model->user_id;
    }

    /**
     * Allow restore only by owner.
     */
    public function restore(User $user, Model $model): bool
    {
        return $user->id === $model->user_id;
    }

    /**
     * Allow force delete only by owner.
     */
    public function forceDelete(User $user, Model $model): bool
    {
        return $user->id === $model->user_id;
    }

    /**
     * Optional: allow sharing only by owner for shareable non-file resources.
     */
    public function share(User $user, Model $model): bool
    {
        return $user->id === $model->user_id;
    }
}
