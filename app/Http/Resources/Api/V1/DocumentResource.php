<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\BaseApiResource;

class DocumentResource extends BaseApiResource
{
    public function toArray($request)
    {
        return array_merge($this->commonFields(), $this->ownershipField(), [
            'title' => $this->title,
            'description' => $this->description,
            'document_type' => $this->document_type,
            'content' => $this->content,
            'extracted_text' => $this->extracted_text,
            'entities' => $this->entities,
            'ai_entities' => $this->ai_entities,
            'ai_summary' => $this->ai_summary ?? null,
            'metadata' => $this->metadata,
            'language' => $this->language,
            'document_date' => $this->document_date?->toISOString(),
            'page_count' => $this->page_count,
            
            // File information
            'file' => $this->whenLoaded('file', function () {
                return [
                    'id' => $this->file->id,
                    'original_filename' => $this->file->original_filename,
                    'mime_type' => $this->file->mime_type,
                    'file_size' => $this->file->file_size,
                    'status' => $this->file->status ?? null,
                ];
            }),
            
            // Relationships
            'category' => $this->whenLoaded('category', function () {
                return new CategoryResource($this->category);
            }),
            
            'tags' => $this->whenLoaded('tags', function () {
                return TagResource::collection($this->tags);
            }),
            
            'shares' => $this->whenLoaded('shares', function () {
                return $this->shares->map(function ($share) {
                    return [
                        'user_id' => $share->shared_with_user_id,
                        'user_name' => $share->sharedWithUser?->name ?? null,
                        'permission' => $share->permission_level,
                        'shared_at' => $share->shared_at?->toISOString(),
                    ];
                });
            }),
            
            // URLs
            'download_url' => $this->when($this->file, function () {
                return route('api.v1.documents.download', $this->id);
            }),
        ]);
    }
}