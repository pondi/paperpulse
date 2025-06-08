<?php

namespace App\Services;

use App\Models\Document;
use App\Models\FileShare;
use App\Models\Receipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DocumentSharedNotification;
use App\Notifications\ReceiptSharedNotification;

class SharingService
{
    /**
     * Share a file (receipt or document) with another user
     */
    public function shareFile(Model $file, User $targetUser, array $options = []): FileShare
    {
        // Validate the file is shareable
        if (!in_array(get_class($file), [Receipt::class, Document::class])) {
            throw new \InvalidArgumentException('Only receipts and documents can be shared');
        }
        
        // Check if the current user owns the file
        if ($file->user_id !== auth()->id()) {
            throw new \UnauthorizedHttpException('You can only share files you own');
        }
        
        // Check if already shared
        $existingShare = FileShare::where([
            'shareable_type' => get_class($file),
            'shareable_id' => $file->id,
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
            'shareable_type' => get_class($file),
            'shareable_id' => $file->id,
            'shared_by_user_id' => auth()->id(),
            'shared_with_user_id' => $targetUser->id,
            'permission' => $options['permission'] ?? 'view',
            'expires_at' => $options['expires_at'] ?? null,
        ]);
        
        // Send notification
        $this->sendShareNotification($file, $targetUser, $share);
        
        return $share;
    }
    
    /**
     * Share with multiple users
     */
    public function shareWithMultiple(Model $file, array $userIds, array $options = []): array
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
    public function unshare(Model $file, User $targetUser): bool
    {
        // Check if the current user owns the file
        if ($file->user_id !== auth()->id()) {
            throw new \UnauthorizedHttpException('You can only unshare files you own');
        }
        
        return FileShare::where([
            'shareable_type' => get_class($file),
            'shareable_id' => $file->id,
            'shared_with_user_id' => $targetUser->id,
        ])->delete() > 0;
    }
    
    /**
     * Get all shares for a file
     */
    public function getShares(Model $file): \Illuminate\Support\Collection
    {
        return FileShare::where([
            'shareable_type' => get_class($file),
            'shareable_id' => $file->id,
        ])
        ->with('sharedWith')
        ->get();
    }
    
    /**
     * Get all files shared with a user
     */
    public function getSharedWithUser(User $user, string $type = null): \Illuminate\Support\Collection
    {
        $query = FileShare::where('shared_with_user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', Carbon::now());
            })
            ->with(['shareable', 'sharedBy']);
        
        if ($type) {
            $modelClass = $type === 'receipt' ? Receipt::class : Document::class;
            $query->where('shareable_type', $modelClass);
        }
        
        return $query->get()->map(function ($share) {
            return [
                'share' => $share,
                'file' => $share->shareable,
                'shared_by' => $share->sharedBy,
                'permission' => $share->permission,
                'expires_at' => $share->expires_at,
            ];
        });
    }
    
    /**
     * Check if a user has access to a file
     */
    public function userHasAccess(Model $file, User $user, string $permission = 'view'): bool
    {
        // Owner always has access
        if ($file->user_id === $user->id) {
            return true;
        }
        
        // Check for share
        $share = FileShare::where([
            'shareable_type' => get_class($file),
            'shareable_id' => $file->id,
            'shared_with_user_id' => $user->id,
        ])
        ->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', Carbon::now());
        })
        ->first();
        
        if (!$share) {
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
    public function getShareableUsers(Model $file): \Illuminate\Support\Collection
    {
        $sharedUserIds = FileShare::where([
            'shareable_type' => get_class($file),
            'shareable_id' => $file->id,
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
        // Verify the current user owns the file
        if ($share->shareable->user_id !== auth()->id()) {
            throw new \UnauthorizedHttpException('You can only update shares for files you own');
        }
        
        $share->update(['permission' => $permission]);
        
        return $share;
    }
    
    /**
     * Create a shareable link
     */
    public function createShareableLink(Model $file, array $options = []): string
    {
        // This would typically create a unique token and store it
        // For now, return a placeholder implementation
        $token = \Str::random(32);
        
        // Store the token in cache or database
        \Cache::put(
            "share_link_{$token}",
            [
                'file_type' => get_class($file),
                'file_id' => $file->id,
                'expires_at' => $options['expires_at'] ?? Carbon::now()->addDays(7),
                'permission' => $options['permission'] ?? 'view',
            ],
            $options['expires_at'] ?? Carbon::now()->addDays(7)
        );
        
        return route('share.link', ['token' => $token]);
    }
    
    /**
     * Send share notification
     */
    protected function sendShareNotification(Model $file, User $targetUser, FileShare $share): void
    {
        if ($file instanceof Receipt) {
            $targetUser->notify(new ReceiptSharedNotification($file, auth()->user(), $share));
        } elseif ($file instanceof Document) {
            $targetUser->notify(new DocumentSharedNotification($file, auth()->user(), $share));
        }
    }
    
    /**
     * Clean up expired shares
     */
    public function cleanupExpiredShares(): int
    {
        return FileShare::where('expires_at', '<', Carbon::now())->delete();
    }
}