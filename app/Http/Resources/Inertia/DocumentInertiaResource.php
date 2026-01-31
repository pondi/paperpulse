<?php

namespace App\Http\Resources\Inertia;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentInertiaResource extends JsonResource
{
    protected bool $detailed = false;

    public static function forIndex($resource): self
    {
        return new self($resource);
    }

    public static function forShow($resource): self
    {
        $instance = new self($resource);
        $instance->detailed = true;

        return $instance;
    }

    public function toArray(Request $request): array
    {
        $fileInfo = $this->buildFileInfo();

        if (! $this->detailed) {
            return [
                'id' => $this->id,
                'title' => $this->title,
                'note' => $this->note,
                'file_name' => $this->file?->fileName,
                'file_type' => $this->file?->fileType,
                'size' => $this->file?->fileSize ?? 0,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
                'category' => $this->category?->only(['id', 'name', 'color']),
                'tags' => $this->tags?->map(fn ($t) => $t->only(['id', 'name']))->values(),
                'shared_with_count' => $this->sharedUsers?->count() ?? 0,
                'file' => $fileInfo,
            ];
        }

        $isOwner = auth()->id() === $this->user_id;
        $sharedUsers = [];
        if ($isOwner && $this->relationLoaded('sharedUsers')) {
            $sharedUsers = $this->sharedUsers->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permission' => $user->pivot->permission ?? 'view',
                'shared_at' => optional($user->pivot->shared_at)->toIso8601String(),
            ])->values();
        }

        $collections = [];
        if ($this->file && $this->file->relationLoaded('collections')) {
            $collections = $this->file->collections->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'color' => $c->color,
            ])->values();
        }

        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'title' => $this->title,
            'summary' => $this->summary,
            'note' => $this->note,
            'category_id' => $this->category_id,
            'tags' => $this->tags?->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'color' => $t->color,
            ])->values(),
            'collections' => $collections,
            'shared_users' => $sharedUsers,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'file' => $fileInfo,
        ];
    }

    private function buildFileInfo(): ?array
    {
        if (! $this->file) {
            return null;
        }

        $extension = $this->file->fileExtension ?? 'pdf';
        $typeFolder = 'documents';
        $hasArchivePdf = ! empty($this->file->s3_archive_path);
        $hasPdfVariant = $hasArchivePdf || strtolower($extension) === 'pdf';
        $pdfUrl = null;

        if ($hasPdfVariant) {
            $pdfUrl = route('documents.serve', [
                'guid' => $this->file->guid,
                'type' => $typeFolder,
                'extension' => 'pdf',
                'variant' => $hasArchivePdf ? 'archive' : 'original',
            ]);
        }

        $previewUrl = null;
        if ($this->file->has_image_preview && $this->file->s3_image_path) {
            $previewUrl = route('documents.serve', [
                'guid' => $this->file->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        $data = [
            'id' => $this->file->id,
            'url' => route('documents.serve', [
                'guid' => $this->file->guid,
                'type' => $typeFolder,
                'extension' => $extension,
            ]),
            'pdfUrl' => $pdfUrl,
            'previewUrl' => $previewUrl,
            'extension' => $extension,
            'size' => $this->file->fileSize,
            'has_preview' => $this->file->has_image_preview,
            'is_pdf' => $hasPdfVariant,
        ];

        if ($this->detailed) {
            $data = array_merge($data, [
                'mime_type' => $this->file->mime_type,
                'guid' => $this->file->guid,
                'uploaded_at' => $this->file->uploaded_at?->toIso8601String(),
                'file_created_at' => $this->file->file_created_at?->toIso8601String(),
                'file_modified_at' => $this->file->file_modified_at?->toIso8601String(),
            ]);
        }

        return $data;
    }
}
