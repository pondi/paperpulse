<?php

namespace App\Http\Controllers;

use App\Services\DocumentService;
use App\Services\ConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

class DocumentController extends Controller
{
    protected $documentService;
    protected $conversionService;

    public function __construct(DocumentService $documentService, ConversionService $conversionService)
    {
        $this->documentService = $documentService;
        $this->conversionService = $conversionService;
    }

    /**
     * Store uploaded documents
     */
    public function store(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf|max:2048',
        ]);

        if($request->hasfile('files'))
        {
            foreach($request->file('files') as $file)
            {
                $this->documentService->processUpload($file);
            }
        }

        return redirect()->route('receipts.index')->with('success', 'Documents uploaded successfully');
    }

    /**
     * Serve a document securely
     * This route should be signed to prevent unauthorized access
     */
    public function serve(Request $request)
    {
        $request->validate([
            'guid' => 'required|string',
            'type' => 'required|string',
            'extension' => 'required|string'
        ]);

        $guid = $request->input('guid');
        $type = $request->input('type');
        $extension = $request->input('extension');

        // Get the document content
        $content = $this->documentService->getDocument($guid, $type, $extension);
        
        if (!$content) {
            Log::error("Failed to serve document", [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension,
                'user_id' => $request->user()->id
            ]);
            abort(404, 'Document not found');
        }

        // Determine content type using ConversionService
        $contentType = $this->conversionService->getContentType($extension);

        // Create a streamed response to handle large files efficiently
        return new StreamedResponse(function () use ($content) {
            echo $content;
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . $guid . '.' . $extension . '"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }

    /**
     * Generate a secure URL for accessing a document
     */
    public function getSecureUrl(Request $request)
    {
        $request->validate([
            'guid' => 'required|string',
            'type' => 'required|string',
            'extension' => 'required|string'
        ]);

        $guid = $request->input('guid');
        $type = $request->input('type');
        $extension = $request->input('extension');

        $url = $this->documentService->getSecureUrl($guid, $type, $extension);

        if (!$url) {
            Log::error("Failed to generate secure URL", [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension,
                'user_id' => $request->user()->id
            ]);
            abort(404, 'Document not found');
        }

        return redirect()->away($url);
    }
} 