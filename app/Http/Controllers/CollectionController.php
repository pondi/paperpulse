<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\CollectionSharingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function __construct(
        protected CollectionService $collectionService,
        protected CollectionSharingService $sharingService
    ) {}

    /**
     * Display a listing of collections.
     */
    public function index(Request $request): Response
    {
        $query = Collection::where('user_id', auth()->id())
            ->withCount('files');

        // Apply search filter
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Apply archive filter
        $showArchived = $request->boolean('archived', false);
        if (! $showArchived) {
            $query->active();
        }

        // Apply sorting
        $sort = $request->get('sort', 'name');
        match ($sort) {
            '-name' => $query->orderBy('name', 'desc'),
            'files' => $query->orderBy('files_count', 'desc'),
            '-files' => $query->orderBy('files_count', 'asc'),
            'created' => $query->orderBy('created_at', 'desc'),
            '-created' => $query->orderBy('created_at', 'asc'),
            default => $query->orderBy('name', 'asc'),
        };

        $collections = $query->paginate(20)->withQueryString();

        return Inertia::render('Collections/Index', [
            'collections' => $collections,
            'filters' => [
                'search' => $request->search,
                'sort' => $request->get('sort', 'name'),
                'archived' => $showArchived,
            ],
        ]);
    }

    /**
     * Get all active collections for dropdown/selector.
     */
    public function all(): JsonResponse
    {
        $collections = $this->collectionService->getActiveCollectionsForSelector(auth()->id());

        return response()->json($collections);
    }

    /**
     * Display collections shared with the current user.
     */
    public function shared(Request $request): Response
    {
        $collections = $this->sharingService->getSharedWithUser(auth()->user());

        // Load additional data for each collection
        $collections->each(fn ($collection) => $collection->loadCount('files'));

        return Inertia::render('Collections/Shared', [
            'collections' => $collections,
        ]);
    }

    /**
     * Store a newly created collection.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $collection = $this->collectionService->create($validated, auth()->id());

        return back()->with('success', __('Collection created successfully.'));
    }

    /**
     * Display the specified collection.
     */
    public function show(Collection $collection): Response
    {
        $this->authorize('view', $collection);

        $collection->loadCount('files');
        $collection->load(['files' => function ($query) {
            $query->with(['primaryReceipt', 'primaryDocument', 'primaryEntity']);
        }]);
        $stats = $this->collectionService->getCollectionStats($collection);
        $shares = $this->sharingService->getShares($collection);

        return Inertia::render('Collections/Show', [
            'collection' => $collection,
            'stats' => $stats,
            'shares' => $shares,
            'isOwner' => $collection->user_id === auth()->id(),
        ]);
    }

    /**
     * Update the specified collection.
     */
    public function update(Request $request, Collection $collection): RedirectResponse
    {
        $this->authorize('update', $collection);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $this->collectionService->update($collection, $validated);

        return back()->with('success', __('Collection updated successfully.'));
    }

    /**
     * Remove the specified collection.
     */
    public function destroy(Collection $collection): RedirectResponse
    {
        $this->authorize('delete', $collection);

        $this->collectionService->delete($collection);

        return redirect()->route('collections.index')
            ->with('success', __('Collection deleted successfully.'));
    }

    /**
     * Archive the specified collection.
     */
    public function archive(Collection $collection): RedirectResponse
    {
        $this->authorize('archive', $collection);

        $this->collectionService->archive($collection);

        return back()->with('success', __('Collection archived successfully.'));
    }

    /**
     * Unarchive the specified collection.
     */
    public function unarchive(Collection $collection): RedirectResponse
    {
        $this->authorize('archive', $collection);

        $this->collectionService->unarchive($collection);

        return back()->with('success', __('Collection restored successfully.'));
    }

    /**
     * Add files to the collection.
     */
    public function addFiles(Request $request, Collection $collection): RedirectResponse|JsonResponse
    {
        $this->authorize('addItems', $collection);

        $validated = $request->validate([
            'file_ids' => 'required|array|min:1',
            'file_ids.*' => 'integer|exists:files,id',
        ]);

        $this->collectionService->addFiles($collection, $validated['file_ids']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Files added to collection.'),
            ]);
        }

        return back()->with('success', __('Files added to collection.'));
    }

    /**
     * Remove files from the collection.
     */
    public function removeFiles(Request $request, Collection $collection): RedirectResponse|JsonResponse
    {
        $this->authorize('removeItems', $collection);

        $validated = $request->validate([
            'file_ids' => 'required|array|min:1',
            'file_ids.*' => 'integer|exists:files,id',
        ]);

        $this->collectionService->removeFiles($collection, $validated['file_ids']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Files removed from collection.'),
            ]);
        }

        return back()->with('success', __('Files removed from collection.'));
    }

    /**
     * Share the collection with another user.
     */
    public function share(Request $request, Collection $collection): RedirectResponse
    {
        $this->authorize('share', $collection);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'permission' => 'nullable|in:view,edit',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $targetUser = User::where('email', $validated['email'])->first();

        if ($targetUser->id === auth()->id()) {
            return back()->withErrors(['email' => 'You cannot share a collection with yourself.']);
        }

        $this->sharingService->shareCollection($collection, $targetUser, [
            'permission' => $validated['permission'] ?? 'view',
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return back()->with('success', __('Collection shared successfully.'));
    }

    /**
     * Remove share from the collection.
     */
    public function unshare(Collection $collection, User $user): RedirectResponse
    {
        $this->authorize('share', $collection);

        $this->sharingService->unshare($collection, $user);

        return back()->with('success', __('Share removed successfully.'));
    }
}
