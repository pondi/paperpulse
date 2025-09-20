<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Services\ConversionService;
use App\Services\Documents\DocumentUploadHandler;
use App\Services\Documents\DocumentUploadValidator;
use App\Services\FileProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FileProcessingController extends Controller
{
    public function __construct()
    {
        // Apply rate limiting middleware to store method
        $this->middleware('throttle:file-uploads')->only('store');
    }

    /**
     * Store uploaded files
     */
    public function store(Request $request, FileProcessingService $fileProcessingService, ConversionService $conversionService)
    {
        $fileType = $request->input('file_type', 'receipt');

        // Different validation rules based on file type
        if ($fileType === 'document') {
            $request->validate([
                'files' => 'required',
                'files.*' => 'required|file|mimes:jpeg,png,jpg,pdf,tiff,tif|max:10240', // 10MB - Textract supported formats only
                'file_type' => 'required|in:receipt,document',
            ]);
        } else {
            $request->validate([
                'files' => 'required',
                'files.*' => 'required|file|mimes:jpeg,png,jpg,pdf,tiff,tif|max:10240', // 10MB - Textract supported formats only
                'file_type' => 'required|in:receipt,document',
            ]);
        }

        try {
            $uploadedFiles = $request->file('files');

            Log::info('(FileProcessingController) [store] - Files received', [
                'has_files' => $request->hasFile('files'),
                'files_count' => is_array($uploadedFiles) ? count($uploadedFiles) : (is_object($uploadedFiles) ? 1 : 0),
                'file_type' => $fileType,
            ]);

            if (! $uploadedFiles) {
                Log::error('(FileProcessingController) [store] - No files found in request');

                return back()->with('error', 'No files were uploaded. Please select files and try again.');
            }

            if (! is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            $result = DocumentUploadHandler::processUploads(
                $uploadedFiles,
                $fileType,
                auth()->id(),
                $fileProcessingService
            );

            if (! empty($result['errors'])) {
                $firstError = reset($result['errors']);
                $firstFile = array_key_first($result['errors']);

                return back()->with('error', 'File validation failed for "'.$firstFile.'": '.$firstError);
            }

            return redirect()->route($fileType === 'document' ? 'documents.index' : 'receipts.index')
                ->with('success', count($result['processed']).' file(s) uploaded successfully');
        } catch (\Exception $e) {
            Log::error('(FileProcessingController) [store] - Failed to upload document', [
                'error' => $e->getMessage(),
                'file_type' => $fileType,
            ]);

            return back()->with('error', 'Failed to upload file: '.$e->getMessage());
        }
    }

    /**
     * Validate an uploaded file for OCR processing
     */
    protected function validateUploadedFile($uploadedFile): array
    {
        return DocumentUploadValidator::validate($uploadedFile);
    }
}
