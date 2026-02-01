<?php

namespace App\Services\Files;

use App\Models\File;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * File Detail Service
 *
 * Single Responsibility: Retrieve detailed file data with appropriate relationships
 * - Loads file with primary entity based on file_type
 * - Eager loads all necessary relationships for efficient queries
 */
class FileDetailService
{
    /**
     * Get file with detailed data and relationships
     *
     * @throws ModelNotFoundException
     */
    public function getFileWithDetails(int $fileId, int $userId): File
    {
        $file = File::where('id', $fileId)
            ->where('user_id', $userId)
            ->firstOrFail();

        return $this->loadRelationships($file);
    }

    /**
     * Load appropriate relationships based on file type
     */
    private function loadRelationships(File $file): File
    {
        // Load primary entity with type-specific nested relationships
        $file->load([
            'primaryEntity.entity' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Receipt::class => ['merchant', 'category', 'tags', 'lineItems'],
                    \App\Models\Document::class => ['category', 'tags'],
                    \App\Models\Invoice::class => ['lineItems'],
                    \App\Models\Contract::class => [],
                    \App\Models\Voucher::class => [],
                    \App\Models\Warranty::class => [],
                    \App\Models\BankStatement::class => ['transactions'],
                ]);
            },
        ]);

        return $file;
    }

    /**
     * Get the primary entity for a file
     */
    public function getPrimaryEntity(File $file): mixed
    {
        return $file->primaryEntity?->entity;
    }
}
