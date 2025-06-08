<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Tag;
use App\Models\FileShare;
use App\Services\ConversionService;
use App\Services\DocumentService;
use App\Services\SharingService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

class DocumentController extends Controller
{
    protected $sharingService;
    
    public function __construct(SharingService $sharingService)
    {
        $this->sharingService = $sharingService;
        
        // Apply rate limiting middleware to store method
        $this->middleware('throttle:file-uploads')->only('store');
    }
    
    /**
     * Display a listing of documents
     */
    public function index(Request $request)
    {
        $query = Document::query()->with(['category', 'tags', 'sharedUsers']);
        
        // Apply search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            });
        }
        
        // Apply category filter
        if ($categoryId = $request->input('category')) {
            $query->where('category_id', $categoryId);
        }
        
        // Apply tag filter
        if ($tag = $request->input('tag')) {
            $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('name', $tag);
            });
        }
        
        // Apply date filters
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        
        // Order by created_at desc by default
        $query->orderBy('created_at', 'desc');
        
        $documents = $query->paginate(20);
        
        // Get user's categories for filters
        $categories = auth()->user()->categories()->orderBy('name')->get();
        
        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category', 'tag', 'date_from', 'date_to']),
        ]);
    }
    
    /**
     * Display the specified document
     */
    public function show(Document $document)
    {
        $this->authorize('view', $document);
        
        $document->load(['category', 'tags', 'sharedUsers', 'file']);
        
        // Get available tags for suggestions
        $availableTags = auth()->user()->tags()->orderBy('name')->get();
        
        // Get user's categories
        $categories = auth()->user()->categories()->orderBy('name')->get();
        
        return Inertia::render('Documents/Show', [
            'document' => $document,
            'categories' => $categories,
            'available_tags' => $availableTags,
        ]);
    }
    
    /**
     * Update the specified document
     */
    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'summary' => 'nullable|string|max:1000',
            'category_id' => 'nullable|exists:categories,id',
            'tags' => 'sometimes|array',
            'tags.*' => 'integer|exists:tags,id',
        ]);
        
        // Update document
        $document->update($validated);
        
        // Sync tags if provided
        if (isset($validated['tags'])) {
            $document->tags()->sync($validated['tags']);
        }
        
        return back()->with('success', 'Document updated successfully');
    }
    
    /**
     * Remove the specified document
     */
    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);
        
        try {
            // Delete from S3
            if ($document->file && $document->file->s3_path) {
                Storage::disk('paperpulse')->delete($document->file->s3_path);
            }
            
            // Delete document (will cascade to relationships)
            $document->delete();
            
            return redirect()->route('documents.index')
                ->with('success', 'Document deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete document', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->with('error', 'Failed to delete document');
        }
    }
    
    /**
     * Download the original document file
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
     * Share document with another user
     */
    public function share(Request $request, Document $document)
    {
        $this->authorize('share', $document);
        
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'permission' => 'required|in:view,edit',
        ]);
        
        try {
            $share = $this->sharingService->shareDocument(
                $document,
                $validated['email'],
                $validated['permission']
            );
            
            return back()->with('success', 'Document shared successfully');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    
    /**
     * Remove document share
     */
    public function unshare(Document $document, int $userId)
    {
        $this->authorize('share', $document);
        
        try {
            $this->sharingService->unshareDocument($document, $userId);
            
            return back()->with('success', 'Share removed successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to remove share');
        }
    }
    
    /**
     * Bulk delete documents
     */
    public function destroyBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:documents,id',
        ]);
        
        $deleted = 0;
        foreach ($validated['ids'] as $id) {
            $document = Document::find($id);
            if ($document && auth()->user()->can('delete', $document)) {
                try {
                    // Delete from S3
                    if ($document->file && $document->file->s3_path) {
                        Storage::disk('paperpulse')->delete($document->file->s3_path);
                    }
                    $document->delete();
                    $deleted++;
                } catch (\Exception $e) {
                    Log::error('Failed to delete document in bulk operation', [
                        'document_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        return back()->with('success', "{$deleted} documents deleted successfully");
    }
    
    /**
     * Bulk download documents
     */
    public function downloadBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:documents,id',
        ]);
        
        // TODO: Implement zip download functionality
        return back()->with('error', 'Bulk download not yet implemented');
    }
    
    /**
     * Get shares for a document (API)
     */
    public function getShares(Document $document)
    {
        $this->authorize('view', $document);
        
        $shares = \App\Models\FileShare::where('file_id', $document->file_id)
            ->where('file_type', 'document')
            ->with('sharedWithUser:id,name,email')
            ->get();
            
        return response()->json($shares);
    }
    
    /**
     * Display shared documents
     */
    public function shared(Request $request)
    {
        $query = Document::query()
            ->join('file_shares', 'documents.id', '=', 'file_shares.document_id')
            ->where('file_shares.shared_with_user_id', auth()->id())
            ->with(['owner', 'category', 'tags'])
            ->select('documents.*', 'file_shares.permission', 'file_shares.created_at as shared_at');
        
        // Apply search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('documents.title', 'like', "%{$search}%")
                  ->orWhere('documents.content', 'like', "%{$search}%");
            });
        }
        
        $documents = $query->orderBy('file_shares.created_at', 'desc')->paginate(20);
        
        return Inertia::render('Documents/Shared', [
            'documents' => $documents,
            'filters' => $request->only(['search']),
        ]);
    }
    
    /**
     * Display document upload page
     */
    public function upload()
    {
        return Inertia::render('Documents/Upload');
    }
    
    /**
     * Display categories page
     */
    public function categories()
    {
        $categories = auth()->user()->categories()
            ->withCount('documents')
            ->withCount('sharedUsers')
            ->with('sharedUsers')
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                $category->is_shared = $category->sharedUsers->contains('id', auth()->id());
                $category->can_edit = $category->user_id === auth()->id();
                return $category;
            });
        
        return Inertia::render('Documents/Categories', [
            'categories' => $categories,
        ]);
    }
    
    /**
     * Add tag to document
     */
    public function addTag(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);
        
        // Find or create tag
        $tag = auth()->user()->tags()->firstOrCreate([
            'name' => strtolower(trim($validated['name']))
        ]);
        
        // Attach tag to document
        $document->tags()->syncWithoutDetaching([$tag->id]);
        
        return back()->with('success', 'Tag added successfully');
    }
    
    /**
     * Remove tag from document
     */
    public function removeTag(Document $document, Tag $tag)
    {
        $this->authorize('update', $document);
        
        $document->tags()->detach($tag->id);
        
        return back()->with('success', 'Tag removed successfully');
    }

    /**
     * Store uploaded documents
     */
    public function store(Request $request, DocumentService $documentService, ConversionService $conversionService)
    {
        $fileType = $request->input('file_type', 'receipt');
        
        // Different validation rules based on file type
        if ($fileType === 'document') {
            $request->validate([
                'files' => 'required',
                'files.*' => 'required|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv|max:51200', // 50MB
                'file_type' => 'required|in:receipt,document',
            ]);
        } else {
            $request->validate([
                'files' => 'required',
                'files.*' => 'required|file|mimes:jpeg,png,jpg,gif,pdf|max:10240', // 10MB
                'file_type' => 'required|in:receipt,document',
            ]);
        }

        try {
            $uploadedFiles = $request->file('files');
            $processedFiles = [];
            
            // Debug logging
            Log::info('(DocumentController) [store] - Files received', [
                'has_files' => $request->hasFile('files'),
                'files_count' => is_array($uploadedFiles) ? count($uploadedFiles) : 'not_array',
                'file_type' => $fileType,
                'all_files' => $request->allFiles(),
            ]);
            
            // Ensure we have files
            if (!$uploadedFiles) {
                Log::error('(DocumentController) [store] - No files found in request');
                return back()->with('error', 'No files were uploaded. Please select files and try again.');
            }
            
            // Ensure we have an array of files
            if (!is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }
            
            foreach ($uploadedFiles as $index => $uploadedFile) {
                Log::info('(DocumentController) [store] - Processing file', [
                    'index' => $index,
                    'filename' => $uploadedFile->getClientOriginalName(),
                    'size' => $uploadedFile->getSize(),
                    'mime' => $uploadedFile->getMimeType(),
                ]);
                
                // Process the upload based on file type
                $result = $documentService->processUpload($uploadedFile, $fileType);
                $processedFiles[] = $result;
                
                Log::info('(DocumentController) [store] - File processed', [
                    'index' => $index,
                    'result' => $result,
                ]);
            }

            return redirect()->route($fileType === 'document' ? 'documents.index' : 'receipts.index')
                ->with('success', count($processedFiles) . ' file(s) uploaded successfully');
        } catch (\Exception $e) {
            Log::error('(DocumentController) [store] - Failed to upload document', [
                'error' => $e->getMessage(),
                'file_type' => $fileType,
            ]);

            return back()->with('error', 'Failed to upload file: ' . $e->getMessage());
        }
    }

    public function serve(Request $request, StorageService $storageService)
    {
        $request->validate([
            'guid' => 'required|string|regex:/^[a-f0-9\-]{36}$/i',
            'type' => 'required|string|in:receipts,image,pdf,documents',
            'extension' => 'required|string|in:jpg,jpeg,png,gif,pdf',
            'user_id' => 'required|integer',
        ]);

        $guid = $request->input('guid');
        $type = $request->input('type');
        $extension = $request->input('extension');
        $userId = $request->input('user_id');

        // Verify user has access to this file
        if ($userId != auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Determine file type and variant
        $fileType = in_array($type, ['receipts', 'receipt']) ? 'receipt' : 'document';
        // For receipts, PDFs are stored as 'original', not 'processed'
        $variant = 'original';

        // Get the document content
        $content = $storageService->getFileByUserAndGuid($userId, $guid, $fileType, $variant, $extension);

        if (! $content) {
            Log::error("(DocumentController) [serve] - Document not found", [
                'guid' => $guid,
                'type' => $type,
                'extension' => $extension,
                'user_id' => $userId,
            ]);

            return response()->json(['error' => 'Document not found'], 404);
        }

        // Map extension to MIME type
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
        ];

        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

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
