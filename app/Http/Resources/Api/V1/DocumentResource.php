<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Document Resource
 *
 * Single Responsibility: Transform document data for API responses
 */
class DocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'summary' => $this->summary,
            'note' => $this->note,
            'document_type' => $this->document_type,
            'document_date' => $this->document_date?->toISOString(),
            'entities' => $this->entities,
            'ai_entities' => $this->ai_entities,
            'metadata' => $this->metadata,
            'language' => $this->language,
            'category' => $this->relationLoaded('category') && $this->category ? CategoryResource::make($this->category) : null,
            'tags' => $this->relationLoaded('tags') && $this->tags ? TagResource::collection($this->tags) : [],
        ];
    }
}
