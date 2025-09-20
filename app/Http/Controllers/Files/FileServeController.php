<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileShare;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileServeController extends Controller
{
    public function serve(Request $request, StorageService $storageService)
    {
        $request->validate([
            'guid' => 'required|string|regex:/^[a-f0-9\-]{36}$/i',
            'type' => 'required|string|in:receipts,image,pdf,documents,preview',
            'extension' => 'required|string|in:jpg,jpeg,png,gif,pdf,JPG,JPEG,PNG,GIF,PDF',
        ]);

        $guid = $request->input('guid');
        $type = $request->input('type');
        $extension = $request->input('extension');

        // Determine file type and variant
        $fileType = in_array($type, ['receipts', 'receipt', 'image']) ? 'receipt' : 'document';
        $variant = 'original';

        // Check if requesting preview
        if ($type === 'preview' || ($type === 'image' && strtolower($extension) === 'jpg')) {
            $variant = 'preview';
        }

        // Look up file by GUID across users
        $file = File::withoutGlobalScope('user')->where('guid', $guid)->first();
        if (! $file) {
            Log::warning('(FileServeController) [serve] - File not found by GUID', [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension,
            ]);

            return response()->json(['error' => 'Document not found'], 404);
        }

        // Authorization: owner, valid share, or admin
        $isOwner = $file->user_id === auth()->id();
        $hasShare = FileShare::active()
            ->where('file_id', $file->id)
            ->where('file_type', $fileType)
            ->where('shared_with_user_id', auth()->id())
            ->exists();

        if (! $isOwner && ! $hasShare && ! (auth()->user()?->is_admin)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // If requesting image/preview but file is PDF, try to serve the preview
        if ($variant === 'preview' && $file->has_image_preview && $file->s3_image_path) {
            // Serve the preview directly from its path
            $content = $storageService->getFile($file->s3_image_path);
            $extension = 'jpg'; // Preview is always JPG
        } else {
            // Get the document content using the actual owner ID
            $content = $storageService->getFileByUserAndGuid($file->user_id, $guid, $fileType, $variant, $extension);
        }

        if (! $content) {
            // If preview was requested but not found, try to fall back to original
            if ($variant === 'preview' && $file->fileExtension) {
                Log::info('(FileServeController) [serve] - Preview not found, falling back to original', [
                    'guid' => $guid,
                    'original_extension' => $file->fileExtension,
                ]);

                $content = $storageService->getFileByUserAndGuid($file->user_id, $guid, $fileType, 'original', $file->fileExtension);
                $extension = $file->fileExtension;
            }

            if (! $content) {
                Log::error('(FileServeController) [serve] - Document not found', [
                    'guid' => $guid,
                    'type' => $type,
                    'extension' => $extension,
                    'user_id' => $file->user_id,
                    'variant' => $variant,
                ]);

                return response()->json(['error' => 'Document not found'], 404);
            }
        }

        // Map extension to MIME type
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'JPG' => 'image/jpeg',
            'JPEG' => 'image/jpeg',
            'PNG' => 'image/png',
            'GIF' => 'image/gif',
            'PDF' => 'application/pdf',
        ];

        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

        // Create a StreamedResponse
        return new StreamedResponse(function () use ($content) {
            echo $content;
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($content),
            'Content-Disposition' => 'inline; filename="document.'.$extension.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
