<?php

namespace App\Http\Controllers;

use App\Services\ConversionService;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct()
    {
        // Apply rate limiting middleware to store method
        $this->middleware('throttle:file-uploads')->only('store');
    }

    /**
     * Store uploaded documents
     */
    public function store(Request $request, DocumentService $documentService, ConversionService $conversionService)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,pdf|max:2048',
        ]);

        try {
            $uploadedFile = $request->file('file');

            // Process the upload
            $processedFiles = $documentService->processUpload($uploadedFile);

            return response()->json([
                'message' => 'File uploaded successfully',
                'data' => $processedFiles,
            ]);
        } catch (\Exception $e) {
            Log::error('(DocumentController) [store] - Failed to upload document', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to upload file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function serve(Request $request, DocumentService $documentService)
    {
        $request->validate([
            'guid' => 'required|string|regex:/^[a-f0-9\-]{36}$/i',
            'type' => 'required|string|in:receipts,image,pdf',
            'extension' => 'required|string|in:jpg,jpeg,png,gif,pdf',
        ]);

        $guid = $request->input('guid');
        $type = $request->input('type');
        $extension = $request->input('extension');

        // Get the document content
        $content = $documentService->getDocument($guid, $type, $extension);

        if (! $content) {
            Log::error("(DocumentController) [serve] - Document not found (guid: {$guid})", [
                'type' => $type,
                'extension' => $extension,
                'user_id' => $request->user()->id,
            ]);

            return response()->json(['error' => 'Document not found'], 404);
        }

        // Map type to MIME type
        $mimeTypes = [
            'image' => 'image/'.$extension,
            'pdf' => 'application/pdf',
        ];

        $mimeType = $mimeTypes[$type] ?? 'application/octet-stream';

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

    public function getSecureUrl(Request $request, DocumentService $documentService)
    {
        $request->validate([
            'file_id' => 'required|integer',
        ]);

        $fileId = $request->input('file_id');

        try {
            $url = $documentService->getSecureUrl($fileId);

            if (! $url) {
                Log::error('(DocumentController) [getSecureUrl] - Could not generate secure URL', [
                    'file_id' => $fileId,
                    'user_id' => $request->user()->id,
                ]);

                return response()->json(['error' => 'Could not generate secure URL'], 500);
            }

            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            Log::error('(DocumentController) [getSecureUrl] - Error generating secure URL', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Error generating secure URL'], 500);
        }
    }
}
