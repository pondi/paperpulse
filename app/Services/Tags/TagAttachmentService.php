<?php

namespace App\Services\Tags;

use App\Contracts\Taggable;
use App\Models\File;
use App\Models\Tag;

class TagAttachmentService
{
    public static function syncTags(Taggable|File $model, array $tagIds): void
    {
        $model->tags()->sync(self::ownedTagIds($model, $tagIds));
    }

    public static function attachTags(Taggable|File $model, array $tagIds): void
    {
        $model->tags()->syncWithoutDetaching(self::ownedTagIds($model, $tagIds));
    }

    /**
     * @return list<int>
     */
    private static function ownedTagIds(Taggable|File $model, array $tagIds): array
    {
        return Tag::query()->withoutGlobalScope('user')
            ->where('user_id', $model->user_id)
            ->whereIn('id', $tagIds)
            ->pluck('id')->all();
    }
}
