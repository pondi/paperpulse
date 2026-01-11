<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;
use App\Services\CollectionSharingService;

/**
 * CollectionPolicy
 *
 * Authorization rules for collections:
 * - viewAny/create: allowed for authenticated users
 * - view: owner or shared user (any permission)
 * - update: owner or shared user with edit permission
 * - delete/share/archive: owner only
 * - addItems/removeItems: owner or shared user with edit permission
 */
class CollectionPolicy
{
    protected function sharingService(): CollectionSharingService
    {
        return app(CollectionSharingService::class);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Collection $collection): bool
    {
        return $this->sharingService()->userHasAccess($collection, $user, 'view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Collection $collection): bool
    {
        return $this->sharingService()->userHasAccess($collection, $user, 'edit');
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $user->id === $collection->user_id;
    }

    public function restore(User $user, Collection $collection): bool
    {
        return $user->id === $collection->user_id;
    }

    public function forceDelete(User $user, Collection $collection): bool
    {
        return $user->id === $collection->user_id;
    }

    public function share(User $user, Collection $collection): bool
    {
        return $user->id === $collection->user_id;
    }

    public function archive(User $user, Collection $collection): bool
    {
        return $user->id === $collection->user_id;
    }

    public function addItems(User $user, Collection $collection): bool
    {
        return $this->sharingService()->userHasAccess($collection, $user, 'edit');
    }

    public function removeItems(User $user, Collection $collection): bool
    {
        return $this->sharingService()->userHasAccess($collection, $user, 'edit');
    }
}
