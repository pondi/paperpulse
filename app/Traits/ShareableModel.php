<?php

namespace App\Traits;

use App\Models\FileShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait ShareableModel
{
    /**
     * Get the shares for this model.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(FileShare::class, 'file_id', 'file_id')
            ->where('file_type', $this->getShareableType());
    }

    /**
     * Get the users that this model is shared with.
     */
    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'file_shares', 'file_id', 'shared_with_user_id')
            ->where('file_shares.file_type', $this->getShareableType())
            ->withPivot('permission', 'shared_at', 'expires_at')
            ->wherePivot('expires_at', '>', now()->toDateTimeString())
            ->orWherePivot('expires_at', null);
    }

    /**
     * Share this model with a user.
     */
    public function shareWith(User $user, string $permission = FileShare::PERMISSION_VIEW): void
    {
        if ($this->user_id === $user->id) {
            throw new \InvalidArgumentException('Cannot share with owner');
        }

        $this->shares()->updateOrCreate([
            'shared_with_user_id' => $user->id,
        ], [
            'shared_by_user_id' => auth()->id(),
            'permission' => $permission,
            'shared_at' => now(),
            'expires_at' => null,
        ]);
    }

    /**
     * Remove share with a user.
     */
    public function unshareWith(User $user): void
    {
        $this->shares()->where('shared_with_user_id', $user->id)->delete();
    }

    /**
     * Check if this model is shared with a user.
     */
    public function isSharedWith(User $user): bool
    {
        return $this->shares()
            ->where('shared_with_user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Check if this model can be viewed by a user.
     */
    public function canBeViewedBy(User $user): bool
    {
        // Owner can always view
        if ($this->user_id === $user->id) {
            return true;
        }

        // Check if there's an active share for this user
        return $this->isSharedWith($user);
    }

    /**
     * Check if this model can be edited by a user.
     */
    public function canBeEditedBy(User $user): bool
    {
        // Owner can always edit
        if ($this->user_id === $user->id) {
            return true;
        }

        // Check if there's an active edit share for this user
        return $this->shares()
            ->where('shared_with_user_id', $user->id)
            ->where('permission', FileShare::PERMISSION_EDIT)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Get the shareable type for FileShare records.
     * Should be implemented by the using model.
     */
    abstract protected function getShareableType(): string;
}
