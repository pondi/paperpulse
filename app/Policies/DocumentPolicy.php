<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\SharingService;

class DocumentPolicy
{
    protected SharingService $sharingService;
    
    public function __construct(SharingService $sharingService)
    {
        $this->sharingService = $sharingService;
    }
    
    /**
     * Determine whether the user can view any documents.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view(User $user, Document $document): bool
    {
        return $this->sharingService->userHasAccess($document, $user, 'view');
    }

    /**
     * Determine whether the user can create documents.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the document.
     */
    public function update(User $user, Document $document): bool
    {
        return $this->sharingService->userHasAccess($document, $user, 'edit');
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, Document $document): bool
    {
        // Only owner can delete
        return $user->id === $document->user_id;
    }

    /**
     * Determine whether the user can share the document.
     */
    public function share(User $user, Document $document): bool
    {
        // Only owner can share
        return $user->id === $document->user_id;
    }
    
    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, Document $document): bool
    {
        return $this->sharingService->userHasAccess($document, $user, 'view');
    }
}