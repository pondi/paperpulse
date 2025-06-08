<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;
use App\Services\SharingService;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReceiptPolicy
{
    use HandlesAuthorization;
    
    protected SharingService $sharingService;
    
    public function __construct(SharingService $sharingService)
    {
        $this->sharingService = $sharingService;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Receipt $receipt): bool
    {
        return $this->sharingService->userHasAccess($receipt, $user, 'view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Receipt $receipt): bool
    {
        return $this->sharingService->userHasAccess($receipt, $user, 'edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Receipt $receipt): bool
    {
        // Only owner can delete
        return $user->id === $receipt->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Receipt $receipt): bool
    {
        return $user->id === $receipt->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Receipt $receipt): bool
    {
        return $user->id === $receipt->user_id;
    }
    
    /**
     * Determine whether the user can share the receipt.
     */
    public function share(User $user, Receipt $receipt): bool
    {
        // Only owner can share
        return $user->id === $receipt->user_id;
    }
}
