<?php

namespace App\Http\Controllers;

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

        $tags = $query->withCount(['documents', 'receipts']);

        switch ($sort) {
            case 'name':
                $tags->orderBy('name', 'asc');
                break;
            case '-name':
                $tags->orderBy('name', 'desc');
                break;
            case 'asc':
                $tags->orderByUsage('asc');
                break;
            case 'desc':
            default:
                $tags->orderByUsage('desc');
                break;
        }

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

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
    public function update(Request $request, Tag $tag)
    {
        $this->authorize('update', $tag);

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $tag->update($validated);

        return back()->with('success', __('Tag updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag)
    {
        $this->authorize('delete', $tag);

        // Detach tag from all documents and receipts
        $tag->documents()->detach();
        $tag->receipts()->detach();

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

        // Move all documents and receipts to the target tag
        foreach ($tag->documents as $document) {
            if (! $targetTag->documents->contains($document->id)) {
                $targetTag->documents()->attach($document->id, ['file_type' => 'document']);
            }
        }

        foreach ($tag->receipts as $receipt) {
            if (! $targetTag->receipts->contains($receipt->id)) {
                $targetTag->receipts()->attach($receipt->id, ['file_type' => 'receipt']);
            }
        }

        // Delete the source tag
        $tag->delete();

        return back()->with('success', __('Tags merged successfully.'));
    }
}
