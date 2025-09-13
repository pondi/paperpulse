<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileShare;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentServeController extends Controller
{
    public function serve(Request $request, StorageService $storageService)
    {
        $request->validate([
            'guid' => 'required|string|regex:/^[a-f0-9\-]{36}$/i',
            'type' => 'required|string|in:receipts,image,pdf,documents',
            'extension' => 'required|string|in:jpg,jpeg,png,gif,pdf',
        ]);

        $guid = $request->input('guid');
        $type = $request->input('type');
        $extension = $request->input('extension');

        // Determine file type and variant
        $fileType = in_array($type, ['receipts', 'receipt']) ? 'receipt' : 'document';
        $variant = 'original';

        // Look up file by GUID across users
        $file = File::withoutGlobalScope('user')->where('guid', $guid)->first();
        if (! $file) {
            Log::warning('(DocumentServeController) [serve] - File not found by GUID', [
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

        // Get the document content using the actual owner ID
        $content = $storageService->getFileByUserAndGuid($file->user_id, $guid, $fileType, $variant, $extension);

        if (! $content) {
            Log::error('(DocumentServeController) [serve] - Document not found', [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension,
                'user_id' => $file->user_id,
            ]);

            return response()->json(['error' => 'Document not found'], 404);
        }

        // Map extension to MIME type
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
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
