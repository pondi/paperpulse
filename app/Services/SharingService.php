<?php

namespace App\Services;

use App\Models\Document;
use App\Models\FileShare;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\DocumentSharedNotification;
use App\Notifications\ReceiptSharedNotification;
use Cache;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Str;

class SharingService
{
    /**
     * Share a file (receipt or document) with another user
     */
    public function shareFile(Receipt|Document $file, User $targetUser, array $options = []): FileShare
    {
        // Validate the file is shareable
        if (! in_array(get_class($file), [Receipt::class, Document::class])) {
            throw new InvalidArgumentException('Only receipts and documents can be shared');
        }

        // Check if the current user owns the file
        if ($file->user_id !== auth()->id()) {
            throw new AuthorizationException('You can only share files you own');
        }

        // Check if already shared
        $fileType = $file instanceof Document ? 'document' : 'receipt';
        $existingShare = FileShare::where([
            'file_type' => $fileType,
            'file_id' => $file->file_id,
            'shared_with_user_id' => $targetUser->id,
        ])->first();

        if ($existingShare) {
            // Update existing share
            $existingShare->update([
                'permission' => $options['permission'] ?? 'view',
                'expires_at' => $options['expires_at'] ?? null,
            ]);

            return $existingShare;
        }

        // Create new share
        $share = FileShare::create([
            'file_type' => $fileType,
            'file_id' => $file->file_id,
            'shared_by_user_id' => auth()->id(),
            'shared_with_user_id' => $targetUser->id,
            'permission' => $options['permission'] ?? 'view',
            'shared_at' => now(),
            'expires_at' => $options['expires_at'] ?? null,
        ]);

        // Send notification
        $this->sendShareNotification($file, $targetUser, $share);

        return $share;
    }

    /**
     * Share with multiple users
     */
    public function shareWithMultiple(Receipt|Document $file, array $userIds, array $options = []): array
    {
        $shares = [];

        DB::transaction(function () use ($file, $userIds, $options, &$shares) {
            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if ($user && $user->id !== auth()->id()) {
                    $shares[] = $this->shareFile($file, $user, $options);
                }
            }
        });

        return $shares;
    }

    /**
     * Remove a share
     */
    public function unshare(Receipt|Document $file, User $targetUser): bool
    {
        // Check if the current user owns the file
        if ($file->user_id !== auth()->id()) {
            throw new AuthorizationException('You can only unshare files you own');
        }

        $fileType = $file instanceof Document ? 'document' : 'receipt';

        return FileShare::where([
            'file_type' => $fileType,
            'file_id' => $file->file_id,
            'shared_with_user_id' => $targetUser->id,
        ])->delete() > 0;
    }

    /**
     * Get all shares for a file
     */
    public function getShares(Receipt|Document $file): Collection
    {
        $fileType = $file instanceof Document ? 'document' : 'receipt';

        return FileShare::where([
            'file_type' => $fileType,
            'file_id' => $file->file_id,
        ])
            ->with('sharedWithUser')
            ->get();
    }

    /**
     * Get all files shared with a user
     */
    public function getSharedWithUser(User $user, ?string $type = null): Collection
    {
        $query = FileShare::where('shared_with_user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->with(['shareable', 'sharedBy']);

        if ($type) {
            $query->where('file_type', $type);
        }

        return $query->get()->map(function ($share) {
            return [
                'share' => $share,
                'file' => $share->shareable(),
                'shared_by' => $share->sharedBy,
                'permission' => $share->permission,
                'expires_at' => $share->expires_at,
            ];
        });
    }

    /**
     * Check if a user has access to a file
     */
    public function userHasAccess(Receipt|Document $file, User $user, string $permission = 'view'): bool
    {
        // Owner always has access
        if ($file->user_id === $user->id) {
            return true;
        }

        // Check for share
        $fileType = $file instanceof Document ? 'document' : 'receipt';
        $share = FileShare::where([
            'file_type' => $fileType,
            'file_id' => $file->file_id,
            'shared_with_user_id' => $user->id,
        ])
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->first();

        if (! $share) {
            return false;
        }

        // Check permission level
        if ($permission === 'edit' && $share->permission === 'view') {
            return false;
        }

        return true;
    }

    /**
     * Get shareable users (exclude current user and already shared users)
     */
    public function getShareableUsers(Receipt|Document $file): Collection
    {
        $fileType = $file instanceof Document ? 'document' : 'receipt';
        $sharedUserIds = FileShare::where([
            'file_type' => $fileType,
            'file_id' => $file->file_id,
        ])->pluck('shared_with_user_id');

        return User::where('id', '!=', auth()->id())
            ->whereNotIn('id', $sharedUserIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Update share permissions
     */
    public function updateSharePermission(FileShare $share, string $permission): FileShare
    {
        // Get the file and verify ownership
        $file = $share->shareable();
        if (! $file || $file->user_id !== auth()->id()) {
            throw new AuthorizationException('You can only update shares for files you own');
        }

        $share->update(['permission' => $permission]);

        return $share;
    }

    /**
     * Send share notification
     */
    protected function sendShareNotification(Receipt|Document $file, User $targetUser, FileShare $share): void
    {
        if ($file instanceof Receipt) {
            $targetUser->notify(new ReceiptSharedNotification($file, auth()->user(), $share));
        } elseif ($file instanceof Document) {
            $targetUser->notify(new DocumentSharedNotification($file, auth()->user(), $share));
        }
    }

    /**
     * Share a document with another user by email
     */
    public function shareDocument(Document $document, string $email, string $permission = 'view'): FileShare
    {
        $targetUser = User::where('email', $email)->firstOrFail();

        if ($targetUser->id === auth()->id()) {
            throw new InvalidArgumentException('You cannot share a document with yourself');
        }

        return $this->shareFile($document, $targetUser, ['permission' => $permission]);
    }

    /**
     * Remove document share with a user
     */
    public function unshareDocument(Document $document, int $userId): bool
    {
        $targetUser = User::findOrFail($userId);

        return $this->unshare($document, $targetUser);
    }

    /**
     * Clean up expired shares
     */
    public function cleanupExpiredShares(): int
    {
        return FileShare::where('expires_at', '<', Carbon::now())->delete();
    }
}
