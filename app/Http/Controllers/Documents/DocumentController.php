<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\BaseResourceController;
use App\Models\Document;
use App\Models\Tag;
use App\Services\DocumentService;
use App\Services\ConversionService;
use App\Traits\ShareableController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends BaseResourceController
{
    use ShareableController;

    protected string $model = Document::class;
    protected string $resource = 'Documents';

    protected array $indexWith = ['category', 'tags', 'sharedUsers', 'file'];
    protected array $showWith = ['category', 'tags', 'sharedUsers', 'file'];

    protected array $searchableFields = ['title', 'content', 'summary'];
    protected array $filterableFields = ['category_id', 'tag'];

    protected array $validationRules = [
        'title' => 'sometimes|string|max:255',
        'summary' => 'nullable|string|max:1000',
        'category_id' => 'nullable|exists:categories,id',
        'tags' => 'sometimes|array',
        'tags.*' => 'integer|exists:tags,id',
    ];

    /**
     * Transform item for index display.
     */
    protected function transformForIndex($document): array
    {
        $fileInfo = null;
        if ($document->file) {
            $extension = $document->file->fileExtension ?? 'pdf';
            $typeFolder = 'documents';
            $fileInfo = [
                'id' => $document->file->id,
                'url' => route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => $extension,
                ]),
                'pdfUrl' => $extension === 'pdf' ? route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                ]) : null,
                'extension' => $extension,
                'size' => $document->file->fileSize,
            ];
        }

        return [
            'id' => $document->id,
            'title' => $document->title,
            'file_name' => $document->file?->fileName,
            'file_type' => $document->file?->fileType,
            'size' => $document->file?->fileSize ?? 0,
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
            'category' => $document->category?->only(['id', 'name', 'color']),
            'tags' => $document->tags?->map(fn ($t) => $t->only(['id', 'name']))->values(),
            'shared_with_count' => $document->sharedUsers?->count() ?? 0,
            'file' => $fileInfo,
        ];
    }

    /**
     * Transform item for show display.
     */
    protected function transformForShow($document): array
    {
        $fileInfo = null;
        if ($document->file) {
            $extension = $document->file->fileExtension ?? 'pdf';
            $typeFolder = 'documents';
            $fileInfo = [
                'id' => $document->file->id,
                'url' => route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => $extension,
                ]),
                'pdfUrl' => $extension === 'pdf' ? route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                ]) : null,
                'extension' => $extension,
                'mime_type' => $document->file->mime_type,
                'size' => $document->file->fileSize,
                'guid' => $document->file->guid,
            ];
        }

        $isOwner = auth()->id() === $document->user_id;

        return [
            'id' => $document->id,
            'title' => $document->title,
            'summary' => $document->summary,
            'category_id' => $document->category_id,
            'tags' => $document->tags,
            // Only owners can see who else this is shared with
            'shared_users' => $isOwner ? $document->sharedUsers : [],
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
            'file' => $fileInfo,
        ];
    }

    /**
     * Get meta data for the show page.
     */
    protected function getShowMeta(): array
    {
        return [
            'categories' => auth()->user()->categories()->orderBy('name')->get(),
            'available_tags' => auth()->user()->tags()->orderBy('name')->get(),
        ];
    }

    /**
     * Apply filter to query.
     */
    protected function applyFilter($query, string $field, $value)
    {
        if ($field === 'tag') {
            return $query->whereHas('tags', function ($q) use ($value) {
                $q->where('name', $value);
            });
        }

        return parent::applyFilter($query, $field, $value);
    }

    /**
     * Prepare data for update.
     */
    protected function prepareForUpdate(array $validated, $document): array
    {
        // Handle tags separately
        if (isset($validated['tags'])) {
            $document->tags()->sync($validated['tags']);
            unset($validated['tags']);
        }

        return $validated;
    }

    /**
     * Hook called before destroy.
     */
    protected function beforeDestroy($document): void
    {
        try {
            // Delete from S3
            if ($document->file && $document->file->s3_path) {
                Storage::disk('paperpulse')->delete($document->file->s3_path);
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete document file from S3', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get shareable type for ShareableController trait.
     */
    protected function getShareableType(): string
    {
        return 'document';
    }

    /**
     * Get route name prefix.
     */
    protected function getRouteName(): string
    {
        return 'documents';
    }

    /**
     * Download the original document file.
     */
    public function download(Document $document)
    {
        $this->authorize('view', $document);

        if (!$document->file || !$document->file->s3_path) {
            abort(404, 'Document file not found');
        }

        try {
            $file = Storage::disk('paperpulse')->get($document->file->s3_path);

            return response($file)
                ->header('Content-Type', $document->file->mime_type)
                ->header('Content-Disposition', 'attachment; filename="' . $document->file_name . '"');
        } catch (\Exception $e) {
            Log::error('Failed to download document', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to download document');
        }
    }

    /**
     * Attach tag to document.
     */
    public function attachTag(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $tag = $document->addTagByName($validated['name']);

        return back()->with('success', 'Tag added successfully');
    }

    /**
     * Detach tag from document.
     */
    public function detachTag(Document $document, Tag $tag)
    {
        $this->authorize('update', $document);

        $document->removeTag($tag);

        return back()->with('success', 'Tag removed successfully');
    }

    /**
     * Display document upload page.
     */
    public function upload()
    {
        return Inertia::render('Documents/Upload');
    }

    /**
     * Display categories page.
     */
    public function categories()
    {
        $categories = auth()->user()->categories()
            ->withCount('documents')
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                $category->can_edit = $category->user_id === auth()->id();
                return $category;
            });

        return Inertia::render('Documents/Categories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Display shared documents.
     */
    public function shared(Request $request)
    {
        $query = Document::query()
            ->join('file_shares', function ($join) {
                $join->on('documents.file_id', '=', 'file_shares.file_id')
                    ->where('file_shares.file_type', '=', 'document');
            })
            ->where('file_shares.shared_with_user_id', auth()->id())
            ->with(['owner', 'category', 'tags', 'file'])
            ->select('documents.*', 'file_shares.permission', 'file_shares.shared_at');

        // Apply search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('documents.title', 'like', "%{$search}%")
                    ->orWhere('documents.content', 'like', "%{$search}%");
            });
        }

        $documents = $query->orderBy('file_shares.shared_at', 'desc')->paginate(20);

        return Inertia::render('Documents/Shared', [
            'documents' => $documents,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store uploaded documents.
     */
    public function store(Request $request)
    {
        $fileType = $request->input('file_type', 'document');

        $request->validate([
            'files' => 'required',
            'files.*' => 'required|file|mimes:jpeg,png,jpg,pdf,tiff,tif|max:10240',
            'file_type' => 'required|in:receipt,document',
        ]);

        try {
            $documentService = app(DocumentService::class);
            $uploadedFiles = $request->file('files');
            $processedFiles = [];

            if (!is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            foreach ($uploadedFiles as $uploadedFile) {
                // Additional file validation before processing
                $fileValidation = $this->validateUploadedFile($uploadedFile);
                if (!$fileValidation['valid']) {
                    Log::error('File validation failed', [
                        'filename' => $uploadedFile->getClientOriginalName(),
                        'error' => $fileValidation['error'],
                    ]);
                    return back()->with('error', 'File validation failed for "'.$uploadedFile->getClientOriginalName().'": '.$fileValidation['error']);
                }

                $result = $documentService->processUpload($uploadedFile, $fileType);
                $processedFiles[] = $result;
            }

            return redirect()->route($fileType === 'document' ? 'documents.index' : 'receipts.index')
                ->with('success', count($processedFiles) . ' file(s) uploaded successfully');
        } catch (\Exception $e) {
            Log::error('Failed to upload document', [
                'error' => $e->getMessage(),
                'file_type' => $fileType,
            ]);

            return back()->with('error', 'Failed to upload file: ' . $e->getMessage());
        }
    }

    /**
     * Validate an uploaded file for OCR processing.
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

        if (!in_array($extension, $supportedExtensions)) {
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

        if (isset($expectedMimeTypes[$extension]) && !in_array($mimeType, $expectedMimeTypes[$extension])) {
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
