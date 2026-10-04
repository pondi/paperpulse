<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\BaseApiResource;
use App\Models\Tag;

/** @mixin Tag */
class TagResource extends BaseApiResource
{
    public function toArray($request)
    {
        return array_merge($this->commonFields(), $this->ownershipField(), [
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'usage_count' => $this->when(isset($this->usage_count), $this->usage_count),
        ]);
    }
}
