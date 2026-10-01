<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\BrowseFoldersRequest;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Http\Resources\Api\V1\CollectionResource;
use App\Models\Collection;
use App\Services\CollectionService;
use App\Services\FolderTreeService;
use Illuminate\Http\Request;

class CollectionController extends BaseApiController
{
    /**
     * List user's collections with optional filtering
     */
    public function index(BrowseFoldersRequest $request)
    {
        $validated = $request->validated();

        $query = Collection::where('user_id', $request->user()->id)
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->withCount('files');

        if (! empty($validated['search'])) {
            $query->search($validated['search']);
        }

        if (isset($validated['archived'])) {
            if ($validated['archived']) {
                $query->archived();
            } else {
                $query->active();
            }
        }

        $query->orderBy('name', 'asc');

        $collections = $query->paginate($validated['per_page'] ?? 50);

        return $this->paginated(CollectionResource::collection($collections));
    }

    /**
     * Create a new collection
     */
    public function store(StoreCollectionRequest $request)
    {
        $validated = $request->validated();

        // Check for existing collection with same name
        $existingCollection = Collection::where('user_id', $request->user()->id)
            ->where('normalized_name', Collection::normalizeIdentity($validated['name']))
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->first();

        if ($existingCollection) {
            return $this->error('A collection with this name already exists', 409, [
                'name' => ['A collection with this name already exists.'],
            ]);
        }

        $collection = app(CollectionService::class)->create($validated, $request->user()->id);

        return $this->success(
            new CollectionResource($collection),
            'Collection created successfully',
            201
        );
    }

    /**
     * Update an existing collection
     */
    public function update(UpdateCollectionRequest $request, Collection $collection)
    {
        // Verify ownership
        if ($collection->user_id !== $request->user()->id) {
            return $this->forbidden('You do not have permission to update this collection');
        }

        $validated = $request->validated();

        // Check for existing collection with same name (excluding current collection)
        if (isset($validated['name'])) {
            $existingCollection = Collection::where('user_id', $request->user()->id)
                ->where('normalized_name', Collection::normalizeIdentity($validated['name']))
                ->where('parent_id', array_key_exists('parent_id', $validated) ? $validated['parent_id'] : $collection->parent_id)
                ->where('id', '!=', $collection->id)
                ->first();

            if ($existingCollection) {
                return $this->error('A collection with this name already exists', 409, [
                    'name' => ['A collection with this name already exists.'],
                ]);
            }
        }

        $collection = app(CollectionService::class)->update($collection, $validated);
        if (array_key_exists('is_archived', $validated)) {
            app(FolderTreeService::class)->archiveTree($collection, $validated['is_archived']);
        }

        return $this->success(
            new CollectionResource($collection->fresh()->loadCount('files')),
            'Collection updated successfully'
        );
    }

    /**
     * Delete a collection
     */
    public function destroy(Request $request, Collection $collection)
    {
        // Verify ownership
        if ($collection->user_id !== $request->user()->id) {
            return $this->forbidden('You do not have permission to delete this collection');
        }

        app(CollectionService::class)->delete($collection);

        return $this->success(null, 'Collection deleted successfully');
    }
}
