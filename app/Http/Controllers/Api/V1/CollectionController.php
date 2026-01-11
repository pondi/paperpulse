<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\CollectionResource;
use App\Http\Resources\Api\V1\CollectionShareResource;
use App\Models\Collection;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\CollectionSharingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends BaseApiController
{
    public function __construct(
        protected CollectionService $collectionService,
        protected CollectionSharingService $sharingService
    ) {}

    /**
     * List collections with optional filtering
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'archived' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Collection::where('user_id', $request->user()->id)
            ->withCount('files');

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        if (! ($validated['archived'] ?? false)) {
            $query->active();
        }

        $collections = $query
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated(CollectionResource::collection($collections));
    }

    /**
     * Get all active collections (for dropdowns/selectors)
     */
    public function all(Request $request): JsonResponse
    {
        $collections = $this->collectionService->getActiveCollectionsForSelector($request->user()->id);

        return $this->success(
            CollectionResource::collection($collections),
            'Collections retrieved successfully'
        );
    }

    /**
     * Get collections shared with the current user
     */
    public function shared(Request $request): JsonResponse
    {
        $collections = $this->sharingService->getSharedWithUser($request->user());

        // Load additional data for each collection
        $collections->each(fn ($collection) => $collection->loadCount('files'));

        return $this->success(
            CollectionResource::collection($collections),
            'Shared collections retrieved successfully'
        );
    }

    /**
     * Store a new collection
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $collection = $this->collectionService->create($validated, $request->user()->id);

        return $this->success(
            new CollectionResource($collection),
            'Collection created successfully',
            201
        );
    }

    /**
     * Get detailed collection information
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            // Bypass user global scope to allow access to shared collections
            // Authorization is handled by the userHasAccess check below
            $collection = Collection::withoutGlobalScope('user')
                ->with(['files', 'shares.sharedWithUser'])
                ->withCount('files')
                ->findOrFail($id);

            if (! $this->sharingService->userHasAccess($collection, $request->user(), 'view')) {
                return $this->forbidden('You do not have access to this collection');
            }

            $stats = $this->collectionService->getCollectionStats($collection);

            return $this->success([
                'collection' => new CollectionResource($collection),
                'stats' => $stats,
                'is_owner' => $collection->user_id === $request->user()->id,
            ], 'Collection details retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Collection not found');
        }
    }

    /**
     * Update a collection
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $collection = Collection::findOrFail($id);

            if (! $this->sharingService->userHasAccess($collection, $request->user(), 'edit')) {
                return $this->forbidden('You do not have permission to edit this collection');
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:100',
                'description' => 'nullable|string|max:500',
                'icon' => 'nullable|string|max:50',
                'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);

            $collection = $this->collectionService->update($collection, $validated);

            return $this->success(
                new CollectionResource($collection),
                'Collection updated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Collection not found');
        }
    }

    /**
     * Delete a collection
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $collection = Collection::findOrFail($id);

            if ($collection->user_id !== $request->user()->id) {
                return $this->forbidden('Only the owner can delete this collection');
            }

            $this->collectionService->delete($collection);

            return $this->success(null, 'Collection deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Collection not found');
        }
    }

    /**
     * Archive a collection
     */
    public function archive(Request $request, int $id): JsonResponse
    {
        try {
            $collection = Collection::findOrFail($id);

            if ($collection->user_id !== $request->user()->id) {
                return $this->forbidden('Only the owner can archive this collection');
            }

            $collection = $this->collectionService->archive($collection);

            return $this->success(
                new CollectionResource($collection),
                'Collection archived successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Collection not found');
        }
    }

    /**
     * Unarchive a collection
     */
    public function unarchive(Request $request, int $id): JsonResponse
    {
        try {
            $collection = Collection::findOrFail($id);

            if ($collection->user_id !== $request->user()->id) {
                return $this->forbidden('Only the owner can unarchive this collection');
            }

            $collection = $this->collectionService->unarchive($collection);

            return $this->success(
                new CollectionResource($collection),
                'Collection restored successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Collection not found');
        }
    }

    /**
     * Add files to a collection
     */
    public function addFiles(Request $request, int $id): JsonResponse
    {
        try {
            $collection = Collection::findOrFail($id);

            if (! $this->sharingService->userHasAccess($collection, $request->user(), 'edit')) {
                return $this->forbidden('You do not have permission to add files to this collection');
            }

            $validated = $request->validate([
                'file_ids' => 'required|array|min:1',
                'file_ids.*' => 'integer|exists:files,id',
            ]);

            $this->collectionService->addFiles($collection, $validated['file_ids'], $request->user()->id);

            return $this->success(
                new CollectionResource($collection->fresh()->loadCount('files')),
                'Files added to collection successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Collection not found');
        }
    }

    /**
     * Remove files from a collection
     */
    public function removeFiles(Request $request, int $id): JsonResponse
    {
        try {
            $collection = Collection::findOrFail($id);

            if (! $this->sharingService->userHasAccess($collection, $request->user(), 'edit')) {
                return $this->forbidden('You do not have permission to remove files from this collection');
            }

            $validated = $request->validate([
                'file_ids' => 'required|array|min:1',
                'file_ids.*' => 'integer|exists:files,id',
            ]);

            $this->collectionService->removeFiles($collection, $validated['file_ids']);

            return $this->success(
                new CollectionResource($collection->fresh()->loadCount('files')),
                'Files removed from collection successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Collection not found');
        }
    }

    /**
     * Share a collection with another user
     */
    public function share(Request $request, int $id): JsonResponse
    {
        try {
            $collection = Collection::findOrFail($id);

            if ($collection->user_id !== $request->user()->id) {
                return $this->forbidden('Only the owner can share this collection');
            }

            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'permission' => 'nullable|in:view,edit',
                'expires_at' => 'nullable|date|after:now',
            ]);

            $targetUser = User::where('email', $validated['email'])->first();

            if ($targetUser->id === $request->user()->id) {
                return $this->error('You cannot share a collection with yourself', 422);
            }

            $share = $this->sharingService->shareCollection($collection, $targetUser, [
                'permission' => $validated['permission'] ?? 'view',
                'expires_at' => $validated['expires_at'] ?? null,
            ]);

            return $this->success(
                new CollectionShareResource($share->load('sharedWithUser')),
                'Collection shared successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Collection not found');
        } catch (AuthorizationException $e) {
            return $this->forbidden($e->getMessage());
        }
    }

    /**
     * Remove share from a collection
     */
    public function unshare(Request $request, int $id, int $userId): JsonResponse
    {
        try {
            $collection = Collection::findOrFail($id);

            if ($collection->user_id !== $request->user()->id) {
                return $this->forbidden('Only the owner can manage shares for this collection');
            }

            $targetUser = User::findOrFail($userId);
            $this->sharingService->unshare($collection, $targetUser);

            return $this->success(null, 'Share removed successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Collection or user not found');
        }
    }

    /**
     * Get shares for a collection
     */
    public function shares(Request $request, int $id): JsonResponse
    {
        try {
            $collection = Collection::findOrFail($id);

            if ($collection->user_id !== $request->user()->id) {
                return $this->forbidden('Only the owner can view shares for this collection');
            }

            $shares = $this->sharingService->getShares($collection);

            return $this->success(
                CollectionShareResource::collection($shares),
                'Shares retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Collection not found');
        }
    }
}
