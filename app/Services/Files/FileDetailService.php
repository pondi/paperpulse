<?php

namespace App\Services\Files;

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Support\AuthorizedEntityRelations;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
                    Receipt::class => ['merchant', 'category', 'tags', 'lineItems'],
                    Document::class => ['category', 'tags'],
                    Invoice::class => ['lineItems'],
                    Contract::class => [],
                    Voucher::class => [],
                    Warranty::class => [],
                    BankStatement::class => ['transactions'],
                ]);
            },
        ]);

        return $file;
    }

    public function loadExtractedEntities(File $file): File
    {
        return $file->load([
            'extractableEntities' => fn ($query) => $query->where('user_id', $file->user_id)->orderByDesc('is_primary')->orderBy('id'),
            'extractableEntities.entity' => function (MorphTo $relation) use ($file): void {
                AuthorizedEntityRelations::load($relation);
                $relation->withoutGlobalScope('user')->where('user_id', $file->user_id)->where('file_id', $file->id);
            },
        ]);
    }

    /**
     * Get the primary entity for a file
     */
    public function getPrimaryEntity(File $file): mixed
    {
        return $file->primaryEntity?->entity;
    }
}
