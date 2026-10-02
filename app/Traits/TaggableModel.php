<?php

namespace App\Traits;

use App\Models\File;
use App\Models\SearchableFilePivot;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trait for models that can be tagged through their associated File.
 *
 * Tags are stored on the File model to survive entity deletion/recreation
 * during reprocessing. Entity models delegate tag operations to their file.
 */
trait TaggableModel
{
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'file_tags', 'file_id', 'tag_id', 'file_id')
            ->using(SearchableFilePivot::class)
            ->withTimestamps();
    }

    public function getTagNames(): array
    {
        return $this->tags->pluck('name')->all();
    }

    /**
     * Add a tag to this model.
     */
    public function addTag(Tag $tag): void
    {
        $file = $this->getFileForTagging();
        $file?->addTag($tag);
    }

    /**
     * Add a tag by name to this model.
     */
    public function addTagByName(string $name): ?Tag
    {
        $file = $this->getFileForTagging();

        return $file?->addTagByName($name);
    }

    /**
     * Remove a tag from this model.
     */
    public function removeTag(Tag $tag): void
    {
        $file = $this->getFileForTagging();
        $file?->removeTag($tag);
    }

    /**
     * Sync tags for this model.
     */
    public function syncTags(array $tagIds): void
    {
        $file = $this->getFileForTagging();
        $file?->syncTags($tagIds);
    }

    /**
     * Check if this model has a specific tag.
     */
    public function hasTag(Tag $tag): bool
    {
        $file = $this->getFileForTagging();

        return $file?->hasTag($tag) ?? false;
    }

    /**
     * Check if this model has a tag by name.
     */
    public function hasTagByName(string $name): bool
    {
        $file = $this->getFileForTagging();
        if (! $file) {
            return false;
        }

        return $file->tags()->where('tags.name', strtolower(trim($name)))->exists();
    }

    /**
     * Get the file for tagging operations.
     */
    protected function getFileForTagging(): ?File
    {
        if (! $this->file_id) {
            return null;
        }

        return $this->file;
    }

    /**
     * Get the taggable type for the pivot table.
     * No longer needed since tags are on File, but kept for backwards compatibility.
     */
    abstract protected function getTaggableType(): string;
}
