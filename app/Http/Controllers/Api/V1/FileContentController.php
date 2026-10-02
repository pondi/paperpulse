<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\File;
use App\Services\Files\StoragePathBuilder;
use App\Services\StorageService;
use App\Support\UploadedContent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileContentController extends BaseApiController
{
    public function show(Request $request, File $file, StorageService $storageService)
    {
        $this->authorize('view', $file);

        $validated = $request->validate([
            'variant' => 'nullable|string|in:original,archive,preview',
            'disposition' => 'nullable|string|in:inline,attachment',
        ]);

        $variant = $validated['variant'] ?? 'original';
        $disposition = $validated['disposition'] ?? 'inline';

        [$path, $extension, $contentType] = $this->resolvePathAndMime($file, $variant);

        if (! $path) {
            return $this->notFound('File not available');
        }

        $stream = $storageService->readStream($path);
        if (! is_resource($stream)) {
            return $this->notFound('File not available');
        }

        $filename = $this->buildFilename($file, $extension);

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, array_merge([
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ], UploadedContent::headers($extension, $filename, $disposition)));
    }

    /**
     * @return array{0: ?string, 1: string, 2: string}
     */
    private function resolvePathAndMime(File $file, string $variant): array
    {
        $path = StoragePathBuilder::variantPath($file, $variant);
        $extension = match ($variant) {
            'archive' => 'pdf',
            'preview' => 'jpg',
            default => strtolower((string) ($file->fileExtension ?: pathinfo((string) $file->fileName, PATHINFO_EXTENSION))),
        };
        $extension = $extension !== '' ? $extension : 'bin';
        $contentType = $variant === 'original' && $file->mime_type ? $file->mime_type : $this->mimeForExtension($extension);

        return [$path, $extension, $contentType];
    }

    private function mimeForExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain; charset=utf-8',
            'csv' => 'text/csv; charset=utf-8',
            default => 'application/octet-stream',
        };
    }

    private function buildFilename(File $file, string $extension): string
    {
        $base = $file->fileName ?: 'file.'.$extension;

        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $base);

        if (! str_ends_with(strtolower($base), '.'.strtolower($extension))) {
            $base .= '.'.$extension;
        }

        return $base;
    }
}
