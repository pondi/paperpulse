<?php

namespace App\Traits;

use App\Models\File;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trait for models that can be tagged through their associated File.
 *
 * Tags are stored on the File model to survive entity deletion/recreation
 * during reprocessing. Entity models delegate tag operations to their file.
 */
trait TaggableModel
{
    /**
     * Get the tags for this model (through the file relationship).
     * Returns an empty collection if no file is associated.
     */
    public function tags(): BelongsToMany
    {
        // If this model has a file, proxy to its tags
        if ($this->file_id && $this->relationLoaded('file') && $this->file) {
            return $this->file->tags();
        }

        // If file exists but not loaded, load it first
        if ($this->file_id) {
            $file = File::find($this->file_id);
            if ($file) {
                return $file->tags();
            }
        }

        // Return an empty relationship if no file
        // This creates a query that will return empty results
        return $this->belongsToMany(Tag::class, 'file_tags', 'file_id', 'tag_id')
            ->whereRaw('1 = 0'); // Always returns empty
    }

    /**
     * Get tag names as an array.
     */
    public function getTagNames(): array
    {
        if (! $this->file_id) {
            return [];
        }

        $file = $this->relationLoaded('file') ? $this->file : File::find($this->file_id);

        return $file?->getTagNames() ?? [];
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

        return $this->relationLoaded('file') ? $this->file : File::find($this->file_id);
    }

    /**
     * Get the taggable type for the pivot table.
     * No longer needed since tags are on File, but kept for backwards compatibility.
     */
    abstract protected function getTaggableType(): string;
}
