<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Services\CollectionSharingService;
use App\Services\SharingService;
use Illuminate\Database\Eloquent\Model;

trait ShareableFilePolicy
{
    /**
     * Lazily resolve the SharingService from the container.
     */
    protected function sharingService(): SharingService
    {
        return app(SharingService::class);
    }

    /**
     * Lazily resolve the CollectionSharingService from the container.
     */
    protected function collectionSharingService(): CollectionSharingService
    {
        return app(CollectionSharingService::class);
    }

    /**
     * Allow listing by default; queries should be scoped elsewhere.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Basic create access is allowed; controllers can add further checks.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * View if user has view access via ownership, share, or shared collection.
     */
    public function view(User $user, Model $model): bool
    {
        // Check direct access via ownership or share
        if ($this->sharingService()->userHasAccess($model, $user, 'view')) {
            return true;
        }

        // Check transitive access via shared collection (if model has a file relationship)
        if (method_exists($model, 'file') && $model->file_id) {
            return $this->collectionSharingService()->userHasTransitiveFileAccess($model->file_id, $user);
        }

        return false;
    }

    /**
     * Update if user has edit access via ownership or edit share.
     */
    public function update(User $user, Model $model): bool
    {
        return $this->sharingService()->userHasAccess($model, $user, 'edit');
    }

    /**
     * Delete only by owner.
     */
    public function delete(User $user, Model $model): bool
    {
        return $user->id === $model->user_id;
    }

    /**
     * Restore only by owner (if applicable).
     */
    public function restore(User $user, Model $model): bool
    {
        return $user->id === $model->user_id;
    }

    /**
     * Force delete only by owner (if applicable).
     */
    public function forceDelete(User $user, Model $model): bool
    {
        return $user->id === $model->user_id;
    }

    /**
     * Share only by owner.
     */
    public function share(User $user, Model $model): bool
    {
        return $user->id === $model->user_id;
    }

    /**
     * Download allowed if view is allowed.
     */
    public function download(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }
}
