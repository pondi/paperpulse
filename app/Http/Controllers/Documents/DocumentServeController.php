<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
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
            'user_id' => 'required|integer',
        ]);

        $guid = $request->input('guid');
        $type = $request->input('type');
        $extension = $request->input('extension');
        $userId = $request->input('user_id');

        // Verify user has access to this file
        if ($userId != auth()->id() && ! auth()->user()->is_admin) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Determine file type and variant
        $fileType = in_array($type, ['receipts', 'receipt']) ? 'receipt' : 'document';
        // For receipts, PDFs are stored as 'original', not 'processed'
        $variant = 'original';

        // Get the document content
        $content = $storageService->getFileByUserAndGuid($userId, $guid, $fileType, $variant, $extension);

        if (! $content) {
            Log::error('(DocumentServeController) [serve] - Document not found', [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension,
                'user_id' => $userId,
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
