<?php

namespace App\Services\Files;

use App\Models\File;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * File Detail Service
 *
 * Single Responsibility: Retrieve detailed file data with appropriate relationships
 * - Loads file with receipt or document based on file_type
 * - Eager loads all necessary relationships for efficient queries
 */
class FileDetailService
{
    /**
     * Get file with detailed data (receipt or document) and relationships
     *
     * @param  int  $fileId
     * @param  int  $userId
     * @return File
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
     *
     * @param  File  $file
     * @return File
     */
    private function loadRelationships(File $file): File
    {
        if ($file->file_type === 'receipt') {
            return $this->loadReceiptRelationships($file);
        }

        if ($file->file_type === 'document') {
            return $this->loadDocumentRelationships($file);
        }

        return $file;
    }

    /**
     * Load receipt with all related data
     *
     * @param  File  $file
     * @return File
     */
    private function loadReceiptRelationships(File $file): File
    {
        $file->load([
            'receipts' => function ($query) {
                $query->latest()
                    ->with([
                        'merchant',
                        'category',
                        'tags',
                        'lineItems' => function ($itemQuery) {
                            $itemQuery->orderBy('id');
                        },
                    ]);
            },
        ]);

        return $file;
    }

    /**
     * Load document with all related data
     *
     * @param  File  $file
     * @return File
     */
    private function loadDocumentRelationships(File $file): File
    {
        $file->load([
            'documents' => function ($query) {
                $query->latest()
                    ->with([
                        'category',
                        'tags',
                    ]);
            },
        ]);

        return $file;
    }

    /**
     * Get the primary receipt for a file
     *
     * @param  File  $file
     * @return mixed
     */
    public function getPrimaryReceipt(File $file)
    {
        return $file->receipts?->first();
    }

    /**
     * Get the primary document for a file
     *
     * @param  File  $file
     * @return mixed
     */
    public function getPrimaryDocument(File $file)
    {
        return $file->documents?->first();
    }
}
