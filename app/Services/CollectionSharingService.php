<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CollectionSharingService
{
    /**
     * Share a collection with another user.
     */
    public function shareCollection(Collection $collection, User $targetUser, array $options = []): CollectionShare
    {
        if ($collection->user_id !== auth()->id()) {
            throw new AuthorizationException('You can only share collections you own');
        }

        if ($targetUser->id === auth()->id()) {
            throw new InvalidArgumentException('You cannot share a collection with yourself');
        }

        $existingShare = CollectionShare::where([
            'collection_id' => $collection->id,
            'shared_with_user_id' => $targetUser->id,
        ])->first();

        if ($existingShare) {
            $existingShare->update([
                'permission' => $options['permission'] ?? 'view',
                'expires_at' => $options['expires_at'] ?? null,
            ]);

            return $existingShare;
        }

        return CollectionShare::create([
            'collection_id' => $collection->id,
            'shared_by_user_id' => auth()->id(),
            'shared_with_user_id' => $targetUser->id,
            'permission' => $options['permission'] ?? 'view',
            'shared_at' => now(),
            'expires_at' => $options['expires_at'] ?? null,
        ]);
    }

    /**
     * Share collection with multiple users.
     *
     * @param  array<int>  $userIds
     * @return array<CollectionShare>
     */
    public function shareWithMultiple(Collection $collection, array $userIds, array $options = []): array
    {
        $shares = [];

        DB::transaction(function () use ($collection, $userIds, $options, &$shares) {
            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if ($user && $user->id !== auth()->id()) {
                    $shares[] = $this->shareCollection($collection, $user, $options);
                }
            }
        });

        return $shares;
    }

    /**
     * Remove a share.
     */
    public function unshare(Collection $collection, User $targetUser): bool
    {
        if ($collection->user_id !== auth()->id()) {
            throw new AuthorizationException('You can only unshare collections you own');
        }

        return CollectionShare::where([
            'collection_id' => $collection->id,
            'shared_with_user_id' => $targetUser->id,
        ])->delete() > 0;
    }

    /**
     * Get all shares for a collection.
     */
    public function getShares(Collection $collection): SupportCollection
    {
        return CollectionShare::where('collection_id', $collection->id)
            ->with('sharedWithUser')
            ->get();
    }

    /**
     * Get all collections shared with a user.
     *
     * @return SupportCollection<int, Collection>
     */
    public function getSharedWithUser(User $user): SupportCollection
    {
        $shares = CollectionShare::where('shared_with_user_id', $user->id)
            ->active()
            ->with(['collection.user'])
            ->get();

        return $shares->map(fn ($share) => $share->collection)->filter()->values();
    }

    /**
     * Check if a user has access to a collection.
     */
    public function userHasAccess(Collection $collection, User $user, string $permission = 'view'): bool
    {
        if ($collection->user_id === $user->id) {
            return true;
        }

        $share = CollectionShare::where([
            'collection_id' => $collection->id,
            'shared_with_user_id' => $user->id,
        ])
            ->active()
            ->first();

        if (! $share) {
            return false;
        }

        if ($permission === 'edit' && $share->permission === 'view') {
            return false;
        }

        return true;
    }

    /**
     * Check if a user has access to a file via a shared collection.
     * This implements transitive access - if file is in a shared collection,
     * the user can view it.
     */
    public function userHasTransitiveFileAccess(int $fileId, User $user): bool
    {
        return CollectionShare::where('shared_with_user_id', $user->id)
            ->active()
            ->whereHas('collection.files', function ($query) use ($fileId) {
                $query->where('files.id', $fileId);
            })
            ->exists();
    }

    /**
     * Get users available to share with (exclude owner and already shared).
     */
    public function getShareableUsers(Collection $collection): SupportCollection
    {
        $sharedUserIds = CollectionShare::where('collection_id', $collection->id)
            ->pluck('shared_with_user_id');

        return User::where('id', '!=', auth()->id())
            ->whereNotIn('id', $sharedUserIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Update share permissions.
     */
    public function updateSharePermission(CollectionShare $share, string $permission): CollectionShare
    {
        if ($share->collection->user_id !== auth()->id()) {
            throw new AuthorizationException('You can only update shares for collections you own');
        }

        $share->update(['permission' => $permission]);

        return $share;
    }

    /**
     * Share a collection by email.
     */
    public function shareByEmail(Collection $collection, string $email, string $permission = 'view'): CollectionShare
    {
        $targetUser = User::where('email', $email)->firstOrFail();

        return $this->shareCollection($collection, $targetUser, ['permission' => $permission]);
    }

    /**
     * Clean up expired shares.
     */
    public function cleanupExpiredShares(): int
    {
        return CollectionShare::where('expires_at', '<', Carbon::now())->delete();
    }

    /**
     * Mark a share as accessed.
     */
    public function markShareAsAccessed(CollectionShare $share): void
    {
        $share->markAsAccessed();
    }
}
