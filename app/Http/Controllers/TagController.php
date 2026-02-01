<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = auth()->user()->tags();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sort = $request->get('sort', 'desc');

        // Note: orderByUsage() already calls withCount('files'), so we only add it for name sorting
        switch ($sort) {
            case 'name':
                $query->withCount('files')->orderBy('name', 'asc');
                break;
            case '-name':
                $query->withCount('files')->orderBy('name', 'desc');
                break;
            case 'asc':
                $query->orderByUsage('asc');
                break;
            case 'desc':
            default:
                $query->orderByUsage('desc');
                break;
        }

        $tags = $query;

        $tags = $tags->paginate(20)->withQueryString();

        return Inertia::render('Tags/Index', [
            'tags' => $tags,
            'filters' => [
                'search' => $request->search,
                'sort' => $request->get('sort', 'desc'),
            ],
        ]);
    }

    /**
     * Get all tags for the dropdown/selector.
     */
    public function all()
    {
        $tags = auth()->user()->tags()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return response()->json($tags);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTagRequest $request)
    {
        $validated = $request->validated();

        $tag = Tag::findOrCreateByName(
            $validated['name'],
            auth()->id(),
            $validated['color'] ?? null
        );

        return back()->with('success', __('Tag created successfully.'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $this->authorize('update', $tag);

        $tag->update($request->validated());

        return back()->with('success', __('Tag updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag)
    {
        $this->authorize('delete', $tag);

        // Detach tag from all files
        $tag->files()->detach();

        $tag->delete();

        return back()->with('success', __('Tag deleted successfully.'));
    }

    /**
     * Merge one tag into another.
     */
    public function merge(Request $request, Tag $tag)
    {
        $this->authorize('update', $tag);

        $validated = $request->validate([
            'target_tag_id' => 'required|exists:tags,id',
        ]);

        $targetTag = Tag::findOrFail($validated['target_tag_id']);
        $this->authorize('update', $targetTag);

        // Ensure we're not merging a tag into itself
        if ($tag->id === $targetTag->id) {
            return back()->withErrors(['target_tag_id' => 'Cannot merge a tag into itself.']);
        }

        // Move all files to the target tag
        foreach ($tag->files as $file) {
            if (! $targetTag->files->contains($file->id)) {
                $targetTag->files()->attach($file->id);
            }
        }

        // Delete the source tag
        $tag->delete();

        return back()->with('success', __('Tags merged successfully.'));
    }
}
