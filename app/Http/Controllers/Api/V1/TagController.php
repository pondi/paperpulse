<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\TagResource;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends BaseApiController
{
    /**
     * List user's tags with optional search
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Tag::where('user_id', $request->user()->id);

        if (! empty($validated['search'])) {
            $query->search($validated['search']);
        }

        $query->orderByUsage('desc');

        $tags = $query->paginate($validated['per_page'] ?? 50);

        return $this->paginated(TagResource::collection($tags));
    }

    /**
     * Create a new tag
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        // Check for existing tag with same name
        $existingTag = Tag::where('user_id', $request->user()->id)
            ->where('name', $validated['name'])
            ->first();

        if ($existingTag) {
            return $this->error('A tag with this name already exists', 409, [
                'name' => ['A tag with this name already exists.'],
            ]);
        }

        $tag = Tag::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? null,
        ]);

        return $this->success(
            new TagResource($tag),
            'Tag created successfully',
            201
        );
    }

    /**
     * Update an existing tag
     */
    public function update(Request $request, Tag $tag)
    {
        // Verify ownership
        if ($tag->user_id !== $request->user()->id) {
            return $this->forbidden('You do not have permission to update this tag');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        // Check for existing tag with same name (excluding current tag)
        if (isset($validated['name'])) {
            $existingTag = Tag::where('user_id', $request->user()->id)
                ->where('name', $validated['name'])
                ->where('id', '!=', $tag->id)
                ->first();

            if ($existingTag) {
                return $this->error('A tag with this name already exists', 409, [
                    'name' => ['A tag with this name already exists.'],
                ]);
            }
        }

        $tag->update($validated);

        return $this->success(
            new TagResource($tag->fresh()),
            'Tag updated successfully'
        );
    }

    /**
     * Delete a tag
     */
    public function destroy(Request $request, Tag $tag)
    {
        // Verify ownership
        if ($tag->user_id !== $request->user()->id) {
            return $this->forbidden('You do not have permission to delete this tag');
        }

        $tag->delete();

        return $this->success(null, 'Tag deleted successfully');
    }
}
