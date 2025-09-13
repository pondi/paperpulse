<?php

namespace App\Traits;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait TaggableModel
{
    /**
     * Get the tags for this model.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'file_tags', 'file_id', 'tag_id')
            ->wherePivot('file_type', $this->getTaggableType())
            ->withTimestamps();
    }

    /**
     * Add a tag to this model.
     */
    public function addTag(Tag $tag): void
    {
        $this->tags()->syncWithoutDetaching([
            $tag->id => ['file_type' => $this->getTaggableType()]
        ]);
    }

    /**
     * Add a tag by name to this model.
     */
    public function addTagByName(string $name): Tag
    {
        $tag = Tag::findOrCreateByName($name, $this->user_id);
        $this->addTag($tag);

        return $tag;
    }

    /**
     * Remove a tag from this model.
     */
    public function removeTag(Tag $tag): void
    {
        $this->tags()->detach($tag->id);
    }

    /**
     * Sync tags for this model.
     */
    public function syncTags(array $tagIds): void
    {
        $pivotData = [];
        foreach ($tagIds as $tagId) {
            $pivotData[$tagId] = ['file_type' => $this->getTaggableType()];
        }
        $this->tags()->sync($pivotData);
    }

    /**
     * Check if this model has a specific tag.
     */
    public function hasTag(Tag $tag): bool
    {
        return $this->tags()->where('tags.id', $tag->id)->exists();
    }

    /**
     * Check if this model has a tag by name.
     */
    public function hasTagByName(string $name): bool
    {
        return $this->tags()->where('tags.name', strtolower(trim($name)))->exists();
    }

    /**
     * Get tag names as an array.
     */
    public function getTagNames(): array
    {
        return $this->tags()->pluck('name')->toArray();
    }

    /**
     * Get the taggable type for the pivot table.
     * Should be implemented by the using model.
     */
    abstract protected function getTaggableType(): string;
}
