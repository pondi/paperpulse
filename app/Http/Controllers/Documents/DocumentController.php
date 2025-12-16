<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\BaseResourceController;
use App\Models\Document;
use App\Models\File;
use App\Models\Tag;
use App\Services\Documents\DocumentTransformer;
use App\Services\Documents\DocumentUploadHandler;
use App\Services\Documents\DocumentUploadValidator;
use App\Services\FileProcessingService;
use App\Services\StorageService;
use App\Services\Tags\TagAttachmentService;
use App\Traits\ShareableController;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends BaseResourceController
{
    use ShareableController;

    protected string $model = Document::class;

    protected string $resource = 'Documents';

    protected array $indexWith = ['category', 'tags', 'sharedUsers', 'file'];

    protected array $showWith = ['category', 'tags', 'sharedUsers', 'file'];

    protected array $searchableFields = ['title', 'content', 'summary', 'note'];

    protected array $filterableFields = ['category_id', 'tag'];

    protected array $validationRules = [
        'title' => 'sometimes|string|max:255',
        'summary' => 'nullable|string|max:1000',
        'note' => 'nullable|string|max:1000',
        'category_id' => 'nullable|exists:categories,id',
        'tags' => 'sometimes|array',
        'tags.*' => 'integer|exists:tags,id',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = $this->model::query()->with($this->indexWith);

        // Apply user scope
        $query->where('user_id', auth()->id());

        // Apply search
        if ($search = $request->input('search')) {
            $query = $this->applySearch($query, $search);
        }

        // Apply filters
        foreach ($this->filterableFields as $field) {
            if ($value = $request->input($field)) {
                $query = $this->applyFilter($query, $field, $value);
            }
        }

        // Apply sorting
        $sortField = $request->input('sort', $this->defaultSort);
        $sortDirection = $request->input('sort_direction', $this->defaultSortDirection);
        $query->orderBy($sortField, $sortDirection);

        $documents = $query->paginate($request->get('per_page', $this->perPage));

        return Inertia::render('Documents/Index', [
            'documents' => $documents->through(fn ($item) => DocumentTransformer::forIndex($item)),
            'categories' => auth()->user()->categories()->orderBy('name')->get(['id', 'name', 'color']),
            'filters' => $this->getFilters($request),
        ]);
    }

    /**
     * Override show to pass props expected by Vue component.
     */
    public function show($id): Response
    {
        $document = $this->model::with($this->showWith)->findOrFail($id);

        $this->authorize('view', $document);

        $meta = $this->getShowMeta();

        return Inertia::render("{$this->resource}/Show", [
            'document' => DocumentTransformer::forShow($document),
            // Flatten meta for Vue expectations
            'categories' => $meta['categories'] ?? [],
            'available_tags' => $meta['available_tags'] ?? [],
        ]);
    }

    /**
     * Transform item for index display.
     */
    protected function transformForIndex($document): array
    {
        return DocumentTransformer::forIndex($document);
    }

    /**
     * Transform item for show display.
     */
    protected function transformForShow($document): array
    {
        return DocumentTransformer::forShow($document);
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
            TagAttachmentService::syncTags($document, $validated['tags'], 'document');
            unset($validated['tags']);
        }

        return $validated;
    }

    /**
     * Delete a document and clean up its file record so it doesn't affect deduplication.
     */
    public function destroy($id)
    {
        $document = Document::with('file')->findOrFail($id);
        $this->authorize('delete', $document);

        $fileId = $document->file_id;

        DB::transaction(function () use ($document, $fileId) {
            // Delete stored file using StorageService and GUID path.
            if ($document->file && $document->file->guid) {
                $storageService = app(StorageService::class);
                $extension = $document->file->fileExtension ?? 'pdf';
                $fullPath = 'documents/'.$document->user_id.'/'.$document->file->guid.'/original.'.$extension;
                $storageService->deleteFile($fullPath);
            }

            $document->delete();

            // Delete the file record if it no longer has any owners.
            if ($fileId) {
                $file = File::find($fileId);
                if ($file && ! $file->receipts()->exists() && ! $file->documents()->exists()) {
                    $file->delete();
                }
            }
        });

        return redirect()->route('documents.index')->with('success', 'Document deleted successfully');
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

        if (! $document->file || ! $document->file->guid) {
            abort(404, 'Document file not found');
        }

        try {
            $storageService = app(StorageService::class);
            $extension = $document->file->fileExtension ?? 'pdf';
            $content = $storageService->getFileByUserAndGuid(
                $document->user_id,
                $document->file->guid,
                'document',
                'original',
                $extension
            );

            if ($content === null) {
                abort(404, 'Document file not found');
            }

            $filename = $document->file->original_filename
                ?? ($document->title ? preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $document->title).'.'.$extension : 'document.'.$extension);

            return response($content)
                ->header('Content-Type', $document->file->mime_type ?? 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        } catch (Exception $e) {
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
            'files.*' => 'required|file|mimes:jpeg,png,jpg,pdf,tiff,tif|max:102400', // 100MB
            'file_type' => 'required|in:receipt,document',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $fileProcessingService = app(FileProcessingService::class);
            $uploadedFiles = $request->file('files');
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
                ]
            );

            if (! empty($result['errors'])) {
                $firstError = reset($result['errors']);
                $firstFile = array_key_first($result['errors']);

                return back()->with('error', 'File validation failed for "'.$firstFile.'": '.$firstError);
            }

            return redirect()->route($fileType === 'document' ? 'documents.index' : 'receipts.index')
                ->with('success', count($result['processed']).' file(s) uploaded successfully');
        } catch (Exception $e) {
            Log::error('Failed to upload document', [
                'error' => $e->getMessage(),
                'file_type' => $fileType,
            ]);

            return back()->with('error', 'Failed to upload file: '.$e->getMessage());
        }
    }

}
