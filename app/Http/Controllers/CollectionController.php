<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrowseFoldersRequest;
use App\Http\Requests\CollectionFilesRequest;
use App\Http\Requests\ShareCollectionRequest;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Models\Collection;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\CollectionSharingService;
use App\Services\FolderTreeService;
use App\Services\PublicCollectionSharingService;
use App\Support\AuthorizedEntityRelations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function __construct(
        protected CollectionService $collectionService,
        protected CollectionSharingService $sharingService,
        protected PublicCollectionSharingService $publicSharingService,
    ) {}

    /**
     * Display a listing of collections.
     */
    public function index(BrowseFoldersRequest $request): Response
    {
        $query = Collection::where('user_id', auth()->id())
            ->where('parent_id', $request->input('parent_id'))
            ->withCount(['files', 'children'])
            ->with(['children' => fn ($query) => $query->active()->orderBy('name')->limit(5)->select('id', 'name', 'parent_id')]);

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
        $sort = $request->input('sort', 'name');
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
            'breadcrumbs' => $request->filled('parent_id') ? app(FolderTreeService::class)->breadcrumbs(Collection::findOrFail($request->integer('parent_id'))) : [],
            'filters' => [
                'search' => $request->search,
                'sort' => $request->input('sort', 'name'),
                'archived' => $showArchived,
                'parent_id' => $request->input('parent_id'),
            ],
        ]);
    }

    public function folders(BrowseFoldersRequest $request): JsonResponse
    {
        $parent = $request->filled('parent_id') ? Collection::findOrFail($request->integer('parent_id')) : null;
        $folders = Collection::query()->where('user_id', $request->user()->id)
            ->where('parent_id', $parent?->id)->active()->orderBy('name')
            ->paginate(50, ['id', 'name', 'parent_id'])->withQueryString();

        return response()->json([
            'folders' => $folders,
            'parent' => $parent ? ['id' => $parent->id, 'name' => $parent->name, 'parent_id' => $parent->parent_id] : null,
            'path' => $parent ? app(FolderTreeService::class)->breadcrumbs($parent) : [],
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
        $collections->each(fn ($collection) => $collection->loadCount(['files' => fn ($query) => $query->withoutGlobalScope('user')]));

        return Inertia::render('Collections/Shared', [
            'collections' => $collections,
        ]);
    }

    /**
     * Store a newly created collection.
     */
    public function store(StoreCollectionRequest $request): RedirectResponse|JsonResponse
    {
        $collection = $this->collectionService->create($request->validated(), auth()->id());

        if ($request->wantsJson()) {
            return response()->json($collection->only('id', 'name', 'color'), 201);
        }

        return back()->with('success', __('Collection created successfully.'));
    }

    /**
     * Display the specified collection.
     */
    public function show(Collection $collection): Response
    {
        $this->authorize('view', $collection);

        $collection->loadCount(['files' => fn ($query) => $query->withoutGlobalScope('user')]);
        $filePage = $collection->files()->withoutGlobalScope('user')
            ->with(['primaryEntity.entity' => fn ($query) => AuthorizedEntityRelations::load($query)])
            ->orderBy('files.id')->paginate(50, ['files.*'], 'files_page');
        $collection->setRelation('files', $filePage->getCollection());
        $children = Collection::accessibleBy(auth()->user())->where('parent_id', $collection->id)
            ->orderBy('name')->paginate(50, ['id', 'name', 'parent_id'], 'children_page');
        $stats = $this->collectionService->getCollectionStats($collection);
        $shares = $this->sharingService->getShares($collection);

        $publicLinks = $collection->user_id === auth()->id()
            ? $this->publicSharingService->getAllLinksForCollection($collection)
            : collect();

        return Inertia::render('Collections/Show', [
            'collection' => $collection,
            'children' => $children,
            'filePagination' => $filePage->toArray()['links'],
            'treePreview' => $collection->user_id === auth()->id() ? app(FolderTreeService::class)->preview($collection) : null,
            'stats' => $stats,
            'shares' => $shares,
            'isOwner' => $collection->user_id === auth()->id(),
            'publicLinks' => $publicLinks,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Collections', 'href' => route('collections.index')],
                ...($collection->user_id === auth()->id() ? app(FolderTreeService::class)->breadcrumbs($collection) : [['label' => $collection->name]]),
            ],
        ]);
    }

    /**
     * Update the specified collection.
     */
    public function update(UpdateCollectionRequest $request, Collection $collection): RedirectResponse
    {
        $this->authorize('update', $collection);
        if ($request->hasAny(['name', 'parent_id', 'is_pinned'])) {
            $this->authorize('manageTree', $collection);
        }

        $this->collectionService->update($collection, $request->validated());

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
    public function addFiles(CollectionFilesRequest $request, Collection $collection): RedirectResponse|JsonResponse
    {
        $this->authorize('addItems', $collection);

        $this->collectionService->addFiles($collection, $request->validated()['file_ids']);

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
    public function removeFiles(CollectionFilesRequest $request, Collection $collection): RedirectResponse|JsonResponse
    {
        $this->authorize('removeItems', $collection);

        $this->collectionService->removeFiles($collection, $request->validated()['file_ids']);

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
    public function share(ShareCollectionRequest $request, Collection $collection): RedirectResponse
    {
        $this->authorize('share', $collection);

        $validated = $request->validated();
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

        abort_unless($collection->shares()->where('shared_with_user_id', $user->id)->exists(), 404);

        $this->sharingService->unshare($collection, $user);

        return back()->with('success', __('Share removed successfully.'));
    }

    public function treePreview(Collection $collection): JsonResponse
    {
        $this->authorize('manageTree', $collection);

        return response()->json(app(FolderTreeService::class)->preview($collection));
    }
}
