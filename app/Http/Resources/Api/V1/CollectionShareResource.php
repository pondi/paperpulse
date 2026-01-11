<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\BaseApiResource;
use App\Models\CollectionShare;

/**
 * @mixin CollectionShare
 */
class CollectionShareResource extends BaseApiResource
{
    public function toArray($request): array
    {
        return array_merge($this->commonFields(), [
            'collection_id' => $this->collection_id,
            'shared_with_user_id' => $this->shared_with_user_id,
            'shared_by_user_id' => $this->shared_by_user_id,
            'permission' => $this->permission,
            'shared_at' => $this->shared_at->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'accessed_at' => $this->accessed_at?->toISOString(),
            'shared_with' => new UserResource($this->whenLoaded('sharedWithUser')),
            'shared_by' => new UserResource($this->whenLoaded('sharedByUser')),
        ]);
    }
}
