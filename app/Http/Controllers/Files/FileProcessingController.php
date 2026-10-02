<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentUploadRequest;
use App\Services\Documents\DocumentUploadHandler;
use App\Services\FileProcessingService;
use Illuminate\Http\RedirectResponse;

class FileProcessingController extends Controller
{
    public function __construct()
    {
        // Apply rate limiting middleware to store method
        $this->middleware('throttle:file-uploads')->only('store');
    }

    /**
     * Store uploaded files.
     */
    public function store(StoreDocumentUploadRequest $request, FileProcessingService $fileProcessingService): RedirectResponse
    {
        $outcomes = DocumentUploadHandler::processUploads(
            $request->file('files'),
            $request->validated('file_type'),
            $request->user()->id,
            $fileProcessingService,
            $request->safe()->only(['note', 'collection_ids', 'tag_ids']),
        );

        return back()->with('upload_results', $outcomes);
    }
}
