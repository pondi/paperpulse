<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Services\ConversionService;
use App\Services\DocumentService;
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
    public function store(Request $request, DocumentService $documentService, ConversionService $conversionService)
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
            $processedFiles = [];

            // Debug logging
            Log::info('(FileProcessingController) [store] - Files received', [
                'has_files' => $request->hasFile('files'),
                'files_count' => is_array($uploadedFiles) ? count($uploadedFiles) : 'not_array',
                'file_type' => $fileType,
                'all_files' => $request->allFiles(),
            ]);

            // Ensure we have files
            if (! $uploadedFiles) {
                Log::error('(FileProcessingController) [store] - No files found in request');

                return back()->with('error', 'No files were uploaded. Please select files and try again.');
            }

            // Ensure we have an array of files
            if (! is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            foreach ($uploadedFiles as $index => $uploadedFile) {
                Log::info('(FileProcessingController) [store] - Processing file', [
                    'index' => $index,
                    'filename' => $uploadedFile->getClientOriginalName(),
                    'size' => $uploadedFile->getSize(),
                    'mime' => $uploadedFile->getMimeType(),
                ]);

                // Additional file validation before processing
                $fileValidation = $this->validateUploadedFile($uploadedFile);
                if (! $fileValidation['valid']) {
                    Log::error('(FileProcessingController) [store] - File validation failed', [
                        'filename' => $uploadedFile->getClientOriginalName(),
                        'error' => $fileValidation['error'],
                    ]);

                    return back()->with('error', 'File validation failed for "'.$uploadedFile->getClientOriginalName().'": '.$fileValidation['error']);
                }

                // Process the upload based on file type
                $result = $documentService->processUpload($uploadedFile, $fileType);
                $processedFiles[] = $result;

                Log::info('(FileProcessingController) [store] - File processed', [
                    'index' => $index,
                    'result' => $result,
                ]);
            }

            return redirect()->route($fileType === 'document' ? 'documents.index' : 'receipts.index')
                ->with('success', count($processedFiles).' file(s) uploaded successfully');
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
        // Check file size (10MB for Textract)
        $maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if ($uploadedFile->getSize() > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds 10MB limit'];
        }

        if ($uploadedFile->getSize() === 0) {
            return ['valid' => false, 'error' => 'File is empty'];
        }

        // Check file extension
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $supportedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'tiff', 'tif'];

        if (! in_array($extension, $supportedExtensions)) {
            return [
                'valid' => false,
                'error' => "Unsupported file format '{$extension}'. Supported formats: ".implode(', ', $supportedExtensions),
            ];
        }

        // Validate MIME type to prevent files with wrong extensions
        $mimeType = $uploadedFile->getMimeType();
        $expectedMimeTypes = [
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'tiff' => ['image/tiff'],
            'tif' => ['image/tiff'],
        ];

        if (isset($expectedMimeTypes[$extension]) && ! in_array($mimeType, $expectedMimeTypes[$extension])) {
            return [
                'valid' => false,
                'error' => "File MIME type '{$mimeType}' doesn't match extension '{$extension}'. File may be corrupted or have wrong extension.",
            ];
        }

        // Try to get temporary file path for deeper validation
        $tempPath = $uploadedFile->getPathname();

        // Basic file integrity check
        if ($extension === 'pdf') {
            // Check PDF header
            $handle = fopen($tempPath, 'rb');
            if ($handle) {
                $header = fread($handle, 5);
                fclose($handle);

                if (substr($header, 0, 4) !== '%PDF') {
                    return ['valid' => false, 'error' => 'Invalid PDF file - missing PDF header'];
                }
            }
        } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'tiff', 'tif'])) {
            // Try to get image info to validate image files
            $imageInfo = @getimagesize($tempPath);
            if ($imageInfo === false) {
                return ['valid' => false, 'error' => 'Invalid or corrupted image file'];
            }
        }

        return ['valid' => true, 'error' => null];
    }
}
