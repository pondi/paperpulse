<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\BaseResourceController;
use App\Http\Resources\Inertia\DocumentInertiaResource;
use App\Models\Document;
use App\Models\File;
use App\Models\Tag;
use App\Services\Documents\DocumentUploadHandler;
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

    protected array $showWith = ['category', 'tags', 'sharedUsers', 'file.collections'];

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
     *
     * Shows all files uploaded as "document" type, regardless of their
     * extracted entity type (Document, Contract, Invoice, etc.)
     */
    public function index(Request $request): Response
    {
        // Query files that were uploaded as "document" type and have been processed
        // Only include files that have an associated entity (data integrity requirement)
        $query = File::query()
            ->where('user_id', auth()->id())
            ->where('file_type', 'document')
            ->where('status', 'completed')
            ->whereHas('primaryEntity')
            ->with('primaryEntity.entity');

        // Apply search on file name
        if ($search = $request->input('search')) {
            $query->where('fileName', 'like', "%{$search}%");
        }

        // TODO: Category and tag filtering need to be updated to work with polymorphic entities
        // For now, we'll skip these filters as they rely on Document model structure

        // Apply sorting
        $sortField = $request->input('sort', 'uploaded_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $files = $query->paginate($request->get('per_page', $this->perPage));

        return Inertia::render('Documents/Index', [
            'documents' => $files->through(function ($file) {
                return $this->transformEntityForIndex($file->primaryEntity->entity, $file);
            }),
            'categories' => auth()->user()->categories()->orderBy('name')->get(['id', 'name', 'color']),
            'filters' => $this->getFilters($request),
        ]);
    }

    /**
     * Transform any entity type for index display.
     */
    protected function transformEntityForIndex($entity, File $file): array
    {
        $entityType = class_basename($entity);

        // Base data structure
        $createdAt = $entity->created_at ?? $file->uploaded_at;
        $updatedAt = $entity->updated_at ?? $file->uploaded_at;

        $data = [
            'id' => $entity->id,
            'file_id' => $file->id,
            'created_at' => $createdAt?->toIso8601String(),
            'updated_at' => $updatedAt?->toIso8601String(),
            'entity_type' => strtolower($entityType),
        ];

        // Extract title based on entity type
        $data['title'] = match ($entityType) {
            'Document' => $entity->title,
            'Contract' => $entity->contract_title ?? $entity->title ?? $file->fileName,
            'Invoice' => 'Invoice from '.($entity->from_name ?? $entity->vendor_name ?? 'Unknown'),
            'Voucher' => $entity->voucher_name ?? $entity->code ?? 'Voucher',
            'Warranty' => 'Warranty: '.($entity->product_name ?? 'Product'),
            'ReturnPolicy' => 'Return Policy: '.($entity->store_name ?? 'Store'),
            'BankStatement' => 'Bank Statement - '.($entity->account_name ?? 'Account'),
            default => $file->fileName
        };

        // Add file size for display
        $data['size'] = $file->fileSize ?? $file->file_size ?? 0;
        $data['file_name'] = $file->fileName ?? $file->original_filename;
        $data['file_type'] = $file->fileType ?? $file->mime_type;

        // Add note if available on the entity
        $data['note'] = $entity->note ?? null;

        // Add category if available (Document entities)
        if ($entityType === 'Document' && $entity->category) {
            $data['category'] = $entity->category;
        }

        // Add tags if available (Document entities)
        if ($entityType === 'Document' && $entity->tags) {
            $data['tags'] = $entity->tags;
        } else {
            $data['tags'] = [];
        }

        // Add shared users count
        $data['shared_with_count'] = 0;

        // Add file preview information
        if ($file->has_image_preview && $file->s3_image_path) {
            $data['file'] = [
                'id' => $file->id,
                'url' => route('documents.serve', [
                    'guid' => $file->guid,
                    'type' => 'documents',
                    'extension' => $file->fileExtension ?? 'pdf',
                ]),
                'pdfUrl' => $file->fileExtension === 'pdf' || $file->s3_archive_path ? route('documents.serve', [
                    'guid' => $file->guid,
                    'type' => 'documents',
                    'extension' => 'pdf',
                    'variant' => $file->s3_archive_path ? 'archive' : 'original',
                ]) : null,
                'previewUrl' => route('documents.serve', [
                    'guid' => $file->guid,
                    'type' => 'preview',
                    'extension' => 'jpg',
                ]),
                'extension' => $file->fileExtension ?? 'pdf',
                'has_preview' => true,
                'is_pdf' => $file->fileExtension === 'pdf' || ! empty($file->s3_archive_path),
            ];
        } else {
            $data['file'] = [
                'id' => $file->id,
                'url' => route('documents.serve', [
                    'guid' => $file->guid,
                    'type' => 'documents',
                    'extension' => $file->fileExtension ?? 'pdf',
                ]),
                'pdfUrl' => null,
                'previewUrl' => null,
                'extension' => $file->fileExtension ?? 'pdf',
                'has_preview' => false,
                'is_pdf' => $file->fileExtension === 'pdf',
            ];
        }

        return $data;
    }

    /**
     * Override show to pass props expected by Vue component.
     * Route binding handles polymorphic entities (see AppServiceProvider).
     *
     * @param  Document  $document  Route-bound Document model
     */
    public function show($document): Response
    {
        $this->authorize('view', $document);

        $meta = $this->getShowMeta();

        return Inertia::render("{$this->resource}/Show", [
            'document' => DocumentInertiaResource::forShow($document),
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
        return DocumentInertiaResource::forIndex($document)->toArray(request());
    }

    /**
     * Transform item for show display.
     */
    protected function transformForShow($document): array
    {
        return DocumentInertiaResource::forShow($document)->toArray(request());
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

        try {
            DB::transaction(function () use ($document, $fileId) {
                // Delete stored file using StorageService and GUID path.
                if ($document->file && $document->file->guid) {
                    try {
                        $storageService = app(StorageService::class);
                        $extension = $document->file->fileExtension ?? 'pdf';
                        $fullPath = 'documents/'.$document->user_id.'/'.$document->file->guid.'/original.'.$extension;
                        $storageService->deleteFile($fullPath);
                    } catch (Exception $e) {
                        Log::warning('Failed to delete S3 file during document deletion', [
                            'document_id' => $document->id,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with deletion even if S3 delete fails
                    }
                }

                // Disable Scout indexing temporarily to avoid Meilisearch errors
                Document::withoutSyncingToSearch(function () use ($document) {
                    $document->delete();
                });

                // Delete the file record if it no longer has any owners.
                if ($fileId) {
                    $file = File::find($fileId);
                    if ($file && ! $file->receipts()->exists() && ! $file->documents()->exists()) {
                        $file->delete();
                    }
                }
            });

            return redirect()->route('documents.index')->with('success', 'Document deleted successfully');
        } catch (Exception $e) {
            Log::error('Failed to delete document', [
                'document_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to delete document: '.$e->getMessage());
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
