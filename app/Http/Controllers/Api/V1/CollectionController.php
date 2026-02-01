<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\Request;

class CollectionController extends BaseApiController
{
    /**
     * List user's collections with optional filtering
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'archived' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Collection::where('user_id', $request->user()->id)
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|in:'.implode(',', Collection::ICONS),
            'color' => 'nullable|string|max:7',
        ]);

        // Check for existing collection with same name
        $existingCollection = Collection::where('user_id', $request->user()->id)
            ->where('name', $validated['name'])
            ->first();

        if ($existingCollection) {
            return $this->error('A collection with this name already exists', 409, [
                'name' => ['A collection with this name already exists.'],
            ]);
        }

        $collection = Collection::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? 'folder',
            'color' => $validated['color'] ?? null,
        ]);

        return $this->success(
            new CollectionResource($collection),
            'Collection created successfully',
            201
        );
    }

    /**
     * Update an existing collection
     */
    public function update(Request $request, Collection $collection)
    {
        // Verify ownership
        if ($collection->user_id !== $request->user()->id) {
            return $this->forbidden('You do not have permission to update this collection');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|in:'.implode(',', Collection::ICONS),
            'color' => 'nullable|string|max:7',
            'is_archived' => 'nullable|boolean',
        ]);

        // Check for existing collection with same name (excluding current collection)
        if (isset($validated['name'])) {
            $existingCollection = Collection::where('user_id', $request->user()->id)
                ->where('name', $validated['name'])
                ->where('id', '!=', $collection->id)
                ->first();

            if ($existingCollection) {
                return $this->error('A collection with this name already exists', 409, [
                    'name' => ['A collection with this name already exists.'],
                ]);
            }
        }

        $collection->update($validated);

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

        $collection->delete();

        return $this->success(null, 'Collection deleted successfully');
    }
}
