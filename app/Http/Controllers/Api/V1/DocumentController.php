<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\StoreDocumentRequest;
use App\Http\Requests\Api\V1\UpdateDocumentRequest;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;

class DocumentController extends BaseApiController
{
    public function index(Request $request)
    {
        $documents = Document::with(['tags', 'category', 'file'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->document_type, function ($query, $documentType) {
                $query->where('document_type', $documentType);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated(DocumentResource::collection($documents));
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);

        $document->load(['tags', 'category', 'file', 'shares.sharedWithUser']);

        return $this->success(new DocumentResource($document));
    }

    public function store(StoreDocumentRequest $request)
    {
        $document = Document::create(array_merge($request->validated(), [
            'user_id' => auth()->id(),
        ]));

        // Sync tags if provided
        if ($request->tag_ids) {
            $document->tags()->sync($request->tag_ids);
        }

        $document->load(['tags', 'category', 'file']);

        return $this->success(
            new DocumentResource($document),
            'Document created successfully',
            201
        );
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        $this->authorize('update', $document);

        $document->update($request->validated());

        // Sync tags if provided
        if ($request->has('tag_ids')) {
            $document->tags()->sync($request->tag_ids ?? []);
        }

        $document->load(['tags', 'category', 'file']);

        return $this->success(
            new DocumentResource($document),
            'Document updated successfully'
        );
    }

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);

        $document->delete();

        return $this->success(null, 'Document deleted successfully');
    }

    public function share(Request $request, Document $document)
    {
        $this->authorize('share', $document);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission' => 'required|in:read,write',
        ]);

        $user = User::findOrFail($request->user_id);
        $document->shareWith($user, $request->permission);

        return $this->success(null, 'Document shared successfully');
    }

    public function unshare(Document $document, $userId)
    {
        $this->authorize('share', $document);

        $user = User::findOrFail($userId);
        $document->unshareWith($user);

        return $this->success(null, 'Document unshared successfully');
    }

    public function download(Document $document)
    {
        $this->authorize('view', $document);

        if (! $document->file) {
            return $this->notFound('File not found for this document');
        }

        // Return download URL or redirect to file download
        return $this->success([
            'download_url' => route('files.download', $document->file->id),
            'filename' => $document->file->original_filename,
            'size' => $document->file->file_size,
            'mime_type' => $document->file->mime_type,
        ], 'Download information retrieved successfully');
    }
}
