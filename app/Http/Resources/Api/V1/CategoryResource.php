<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\BaseApiResource;

class CategoryResource extends BaseApiResource
{
    public function toArray($request)
    {
        return array_merge($this->commonFields(), $this->ownershipField(), [
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'icon' => $this->icon,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ]);
    }
}