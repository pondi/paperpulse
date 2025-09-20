<?php

namespace App\Contracts\Services;

use App\Models\PulseDavFile;
use App\Models\User;

interface PulseDavFolderContract
{
    /**
     * Build hierarchical folder structure from S3 objects
     */
    public function buildFolderHierarchy(array $items): array;

    /**
     * Get folder contents for a specific path
     */
    public function getFolderContents(User $user, string $folderPath = ''): array;

    /**
     * Update folder tags
     */
    public function updateFolderTags(User $user, string $folderPath, array $tagIds): PulseDavFile;

    /**
     * Get folder statistics
     */
    public function getFolderStats(User $user, ?string $folderPath = null): array;

    /**
     * Get all folders for a user
     */
    public function getUserFolders(User $user): array;

    /**
     * Create a virtual folder structure
     */
    public function createVirtualFolder(User $user, string $folderPath): PulseDavFile;

    /**
     * Delete a folder and all its contents
     */
    public function deleteFolder(User $user, string $folderPath): int;
}
