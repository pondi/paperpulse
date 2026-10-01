<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\BaseApiResource;

class CollectionResource extends BaseApiResource
{
    public function toArray($request)
    {
        return array_merge($this->commonFields(), $this->ownershipField(), [
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'folder_type' => $this->folder_type,
            'is_pinned' => $this->is_pinned,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'is_archived' => $this->is_archived,
            'files_count' => $this->when(isset($this->files_count), $this->files_count),
        ]);
    }
}
