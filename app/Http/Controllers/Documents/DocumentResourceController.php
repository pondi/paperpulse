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

class DocumentResourceController extends BaseResourceController
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
                    'user_id' => $document->file->user_id,
                ]),
                'pdfUrl' => $extension === 'pdf' ? route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                    'user_id' => $document->file->user_id,
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
                    'user_id' => $document->file->user_id,
                ]),
                'pdfUrl' => $extension === 'pdf' ? route('documents.serve', [
                    'guid' => $document->file->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                    'user_id' => $document->file->user_id,
                ]) : null,
                'extension' => $extension,
                'mime_type' => $document->file->mime_type,
                'size' => $document->file->fileSize,
                'guid' => $document->file->guid,
            ];
        }

        return [
            'id' => $document->id,
            'title' => $document->title,
            'summary' => $document->summary,
            'category_id' => $document->category_id,
            'tags' => $document->tags,
            'shared_users' => $document->sharedUsers,
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
     * Store uploaded documents.
     */
    public function upload(Request $request, DocumentService $documentService, ConversionService $conversionService)
    {
        $fileType = $request->input('file_type', 'document');

        $request->validate([
            'files' => 'required',
            'files.*' => 'required|file|mimes:jpeg,png,jpg,pdf,tiff,tif|max:10240',
            'file_type' => 'required|in:receipt,document',
        ]);

        try {
            $uploadedFiles = $request->file('files');
            $processedFiles = [];

            if (!is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            foreach ($uploadedFiles as $uploadedFile) {
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
}