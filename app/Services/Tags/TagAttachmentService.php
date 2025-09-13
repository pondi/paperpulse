<?php

namespace App\Services\Tags;

use Illuminate\Database\Eloquent\Model;

/**
 * Handles consistent tag attachment to models with file_type pivot data.
 *
 * Ensures all tag operations include the required file_type pivot field
 * to prevent database constraint violations.
 */
class TagAttachmentService
{
    /**
     * Sync tags with proper file_type pivot data.
     *
     * @param  Model  $model  The model to attach tags to (Document or Receipt)
     * @param  array  $tagIds  Array of tag IDs to sync
     * @param  string  $fileType  The file type ('document' or 'receipt')
     */
    public static function syncTags(Model $model, array $tagIds, string $fileType): void
    {
        if (empty($tagIds)) {
            $model->tags()->sync([]);

            return;
        }

        $pivotData = [];
        foreach ($tagIds as $tagId) {
            $pivotData[$tagId] = ['file_type' => $fileType];
        }

        $model->tags()->sync($pivotData);
    }

    /**
     * Attach tags without detaching existing ones.
     *
     * @param  Model  $model  The model to attach tags to
     * @param  array  $tagIds  Array of tag IDs to attach
     * @param  string  $fileType  The file type ('document' or 'receipt')
     */
    public static function attachTags(Model $model, array $tagIds, string $fileType): void
    {
        if (empty($tagIds)) {
            return;
        }

        $pivotData = [];
        foreach ($tagIds as $tagId) {
            $pivotData[$tagId] = ['file_type' => $fileType];
        }

        $model->tags()->syncWithoutDetaching($pivotData);
    }
}
