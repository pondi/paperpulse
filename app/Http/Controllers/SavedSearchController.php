<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSearchRequest;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Library/Views', [
            'views' => SavedSearch::query()->where('user_id', $request->user()->id)
                ->orderByDesc('is_pinned')->orderBy('name')->get(['id', 'name', 'scope', 'filters', 'is_pinned']),
        ]);
    }

    public function store(SaveSearchRequest $request): RedirectResponse
    {
        $saved = SavedSearch::create([...$request->validated(), 'user_id' => $request->user()->id]);

        return redirect()->route('saved-searches.show', $saved)->with('success', 'View saved. It will show current matching documents whenever you open it.');
    }

    public function show(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);

        return redirect()->route($savedSearch->scope === 'search' ? 'search' : 'library.index', [
            ...array_filter($savedSearch->filters, fn ($value): bool => $value !== null && $value !== ''),
            'saved_search' => $savedSearch->id,
        ]);
    }

    public function update(SaveSearchRequest $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);
        $savedSearch->update($request->validated());

        return back()->with('success', 'Saved view updated.');
    }

    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);
        $savedSearch->delete();

        return redirect()->route('saved-searches.index')->with('success', 'Saved view removed. Your documents are still in the library.');
    }
}
