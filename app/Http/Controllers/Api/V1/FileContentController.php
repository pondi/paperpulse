<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\File;
use App\Services\StorageService;
use App\Services\Files\StoragePathBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileContentController extends BaseApiController
{
    public function show(Request $request, File $file, StorageService $storageService)
    {
        if ($file->user_id !== $request->user()->id) {
            return $this->forbidden('You do not have access to this file');
        }

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
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array{0: ?string, 1: string, 2: string}
     */
    private function resolvePathAndMime(File $file, string $variant): array
    {
        $fileType = $file->file_type === 'receipt' ? 'receipt' : 'document';

        if ($variant === 'preview') {
            if ($file->has_image_preview && $file->s3_image_path) {
                return [$file->s3_image_path, 'jpg', 'image/jpeg'];
            }

            if ($file->guid) {
                return [StoragePathBuilder::storagePath($file->user_id, $file->guid, $fileType, 'preview', 'jpg'), 'jpg', 'image/jpeg'];
            }

            $variant = 'original';
        }

        if ($variant === 'archive') {
            if (! empty($file->s3_converted_path)) {
                return [$file->s3_converted_path, 'pdf', 'application/pdf'];
            }

            if ($file->guid) {
                return [StoragePathBuilder::storagePath($file->user_id, $file->guid, $fileType, 'archive', 'pdf'), 'pdf', 'application/pdf'];
            }

            if (strtolower((string) ($file->fileExtension ?? '')) === 'pdf' && ! empty($file->s3_original_path)) {
                return [$file->s3_original_path, 'pdf', 'application/pdf'];
            }

            return [null, 'pdf', 'application/pdf'];
        }

        $extension = strtolower((string) ($file->fileExtension ?? pathinfo((string) $file->original_filename, PATHINFO_EXTENSION) ?? ''));
        $extension = $extension !== '' ? $extension : 'bin';

        $contentType = $file->mime_type ?: $this->mimeForExtension($extension);

        if (! empty($file->s3_original_path)) {
            return [$file->s3_original_path, $extension, $contentType];
        }

        if ($file->guid) {
            return [StoragePathBuilder::storagePath($file->user_id, $file->guid, $fileType, 'original', $extension), $extension, $contentType];
        }

        return [null, $extension, $contentType];
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
        $base = $file->original_filename ?: ($file->fileName ?: 'file.'.$extension);

        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $base);

        if (! str_ends_with(strtolower($base), '.'.strtolower($extension))) {
            $base .= '.'.$extension;
        }

        return $base;
    }
}
