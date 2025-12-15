<?php

namespace App\Services\Files;

use App\Models\File;

class FileTransformer
{
    public static function forIndex(File $file): array
    {
        $typeFolder = $file->file_type === 'document' ? 'documents' : 'receipts';
        $extension = $file->fileExtension ?? 'pdf';

        $previewUrl = null;
        if ($file->has_image_preview && $file->s3_image_path) {
            $previewUrl = route('documents.serve', [
                'guid' => $file->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        return [
            'id' => $file->id,
            'guid' => $file->guid,
            'name' => $file->fileName,
            'file_type' => $file->file_type,
            'status' => $file->status,
            'uploaded_at' => $file->uploaded_at?->toIso8601String(),
            'extension' => $extension,
            'mime_type' => $file->fileType,
            'has_preview' => (bool) $file->has_image_preview,
            'previewUrl' => $previewUrl,
            'viewUrl' => route('documents.serve', [
                'guid' => $file->guid,
                'type' => $typeFolder,
                'extension' => $extension,
                'variant' => 'original',
            ]),
        ];
    }
}

