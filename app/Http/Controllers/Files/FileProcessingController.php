<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Services\ConversionService;
use App\Services\Documents\DocumentUploadHandler;
use App\Services\FileProcessingService;
use Exception;
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
                // Office documents + images + PDFs
                'files.*' => 'required|file|mimes:jpeg,png,jpg,pdf,tiff,tif,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,rtf,txt,html,csv|max:102400', // 100MB
                'file_type' => 'required|in:receipt,document',
                'note' => 'nullable|string|max:1000',
                'collection_ids' => 'nullable|array',
                'collection_ids.*' => 'integer|exists:collections,id',
            ]);
        } else {
            $request->validate([
                'files' => 'required',
                // Receipts: only images and PDFs
                'files.*' => 'required|file|mimes:jpeg,png,jpg,pdf,tiff,tif|max:102400', // 100MB
                'file_type' => 'required|in:receipt,document',
                'note' => 'nullable|string|max:1000',
                'collection_ids' => 'nullable|array',
                'collection_ids.*' => 'integer|exists:collections,id',
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
                $fileProcessingService,
                [
                    'note' => $request->input('note'),
                    'collection_ids' => $request->input('collection_ids', []),
                ]
            );

            // Handle errors (validation failures)
            if (! empty($result['errors'])) {
                $firstError = reset($result['errors']);
                $firstFile = array_key_first($result['errors']);

                return back()->with('error', 'File validation failed for "'.$firstFile.'": '.$firstError);
            }

            // Build success/warning message
            $messages = [];
            $processedCount = count($result['processed']);
            $duplicateCount = count($result['duplicates']);

            if ($processedCount > 0) {
                $messages[] = $processedCount.' file(s) uploaded successfully';
            }

            if ($duplicateCount > 0) {
                $duplicateFiles = array_keys($result['duplicates']);
                if ($duplicateCount === 1) {
                    $messages[] = '"'.$duplicateFiles[0].'" was skipped (duplicate file)';
                } else {
                    $messages[] = $duplicateCount.' file(s) skipped (duplicates)';
                }
            }

            // Determine flash message type and content
            if ($processedCount > 0 && $duplicateCount > 0) {
                // Mixed result - show as warning
                return redirect()->route($fileType === 'document' ? 'documents.index' : 'receipts.index')
                    ->with('warning', implode('. ', $messages));
            } elseif ($processedCount > 0) {
                // All successful
                return redirect()->route($fileType === 'document' ? 'documents.index' : 'receipts.index')
                    ->with('success', $messages[0]);
            } elseif ($duplicateCount > 0) {
                // All duplicates
                return redirect()->route($fileType === 'document' ? 'documents.index' : 'receipts.index')
                    ->with('info', implode('. ', $messages));
            } else {
                // No files processed
                return back()->with('error', 'No files were processed');
            }
        } catch (Exception $e) {
            Log::error('(FileProcessingController) [store] - Failed to upload document', [
                'error' => $e->getMessage(),
                'file_type' => $fileType,
            ]);

            return back()->with('error', 'Failed to upload file: '.$e->getMessage());
        }
    }
}
