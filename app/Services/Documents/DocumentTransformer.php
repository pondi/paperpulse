<?php

namespace App\Services\Documents;

use App\Models\Document;

class DocumentTransformer
{
    public static function forIndex(Document $document): array
    {
        $fileInfo = null;
        if ($document->file) {
            $extension = $document->file->fileExtension ?? 'pdf';
            $typeFolder = 'documents';
            $fileInfo = [
                'id' => $document->file->id,
                'url' => route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => $extension,
                ]),
                'pdfUrl' => $extension === 'pdf' ? route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                ]) : null,
                'extension' => $extension,
                'size' => $document->file->fileSize,
            ];
        }

        return [
            'id' => $document->id,
            'title' => $document->title,
            'file_name' => $document->file?->fileName,
            'file_type' => $document->file?->fileType,
            'size' => $document->file?->fileSize ?? 0,
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
            'category' => $document->category?->only(['id', 'name', 'color']),
            'tags' => $document->tags?->map(fn ($t) => $t->only(['id', 'name']))->values(),
            'shared_with_count' => $document->sharedUsers?->count() ?? 0,
            'file' => $fileInfo,
        ];
    }

    public static function forShow(Document $document): array
    {
        $fileInfo = null;
        if ($document->file) {
            $extension = $document->file->fileExtension ?? 'pdf';
            $typeFolder = 'documents';
            $fileInfo = [
                'id' => $document->file->id,
                'url' => route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => $extension,
                ]),
                'pdfUrl' => $extension === 'pdf' ? route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                ]) : null,
                'extension' => $extension,
                'mime_type' => $document->file->mime_type,
                'size' => $document->file->fileSize,
                'guid' => $document->file->guid,
            ];
        }

        $isOwner = auth()->id() === $document->user_id;
        $sharedUsers = [];
        if ($isOwner && $document->relationLoaded('sharedUsers')) {
            $sharedUsers = $document->sharedUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'permission' => $user->pivot->permission ?? 'view',
                    'shared_at' => optional($user->pivot->shared_at)->toIso8601String(),
                ];
            })->values();
        }

        return [
            'id' => $document->id,
            'title' => $document->title,
            'summary' => $document->summary,
            'category_id' => $document->category_id,
            'tags' => $document->tags?->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'color' => $t->color,
            ])->values(),
            'shared_users' => $sharedUsers,
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
            'file' => $fileInfo,
        ];
    }
}

