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

            // Check if there's an archive PDF available
            $hasArchivePdf = ! empty($document->file->s3_archive_path);
            $hasPdfVariant = $hasArchivePdf || strtolower($extension) === 'pdf';
            $pdfUrl = null;

            if ($hasPdfVariant) {
                $pdfUrl = route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                    'variant' => $hasArchivePdf ? 'archive' : 'original',
                ]);
            }

            // Generate preview URL if available
            $previewUrl = null;
            if ($document->file->has_image_preview && $document->file->s3_image_path) {
                $previewUrl = route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => 'preview',
                    'extension' => 'jpg',
                ]);
            }

            $fileInfo = [
                'id' => $document->file->id,
                'url' => route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => $extension,
                ]),
                'pdfUrl' => $pdfUrl,
                'previewUrl' => $previewUrl,
                'extension' => $extension,
                'size' => $document->file->fileSize,
                'has_preview' => $document->file->has_image_preview,
                'is_pdf' => $hasPdfVariant,
            ];
        }

        return [
            'id' => $document->id,
            'title' => $document->title,
            'note' => $document->note,
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

            // Check if there's an archive PDF available
            $hasArchivePdf = ! empty($document->file->s3_archive_path);
            $hasPdfVariant = $hasArchivePdf || strtolower($extension) === 'pdf';
            $pdfUrl = null;

            if ($hasPdfVariant) {
                $pdfUrl = route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                    'variant' => $hasArchivePdf ? 'archive' : 'original',
                ]);
            }

            // Generate preview URL if available
            $previewUrl = null;
            if ($document->file->has_image_preview && $document->file->s3_image_path) {
                $previewUrl = route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => 'preview',
                    'extension' => 'jpg',
                ]);
            }

            $fileInfo = [
                'id' => $document->file->id,
                'url' => route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => $extension,
                ]),
                'pdfUrl' => $pdfUrl,
                'previewUrl' => $previewUrl,
                'extension' => $extension,
                'mime_type' => $document->file->mime_type,
                'size' => $document->file->fileSize,
                'guid' => $document->file->guid,
                'has_preview' => $document->file->has_image_preview,
                'is_pdf' => $hasPdfVariant,
                'uploaded_at' => $document->file->uploaded_at?->toIso8601String(),
                'file_created_at' => $document->file->file_created_at?->toIso8601String(),
                'file_modified_at' => $document->file->file_modified_at?->toIso8601String(),
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

        // Get collections from the file relationship
        $collections = [];
        if ($document->file && $document->file->relationLoaded('collections')) {
            $collections = $document->file->collections->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'color' => $c->color,
            ])->values();
        }

        return [
            'id' => $document->id,
            'file_id' => $document->file_id,
            'title' => $document->title,
            'summary' => $document->summary,
            'note' => $document->note,
            'category_id' => $document->category_id,
            'tags' => $document->tags?->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'color' => $t->color,
            ])->values(),
            'collections' => $collections,
            'shared_users' => $sharedUsers,
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
            'file' => $fileInfo,
        ];
    }
}
