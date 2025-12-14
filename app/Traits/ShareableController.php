<?php

namespace App\Traits;

use App\Models\User;
use App\Services\SharingService;
use Exception;
use Illuminate\Http\Request;

trait ShareableController
{
    /**
     * The sharing service instance.
     */
    protected ?SharingService $sharingService = null;

    /**
     * Get the sharing service.
     */
    protected function getSharingService(): SharingService
    {
        if (! $this->sharingService) {
            $this->sharingService = app(SharingService::class);
        }

        return $this->sharingService;
    }

    /**
     * Share a resource with another user.
     */
    public function share(Request $request, $id)
    {
        $item = $this->model::findOrFail($id);
        $this->authorize('share', $item);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'permission' => 'required|in:view,edit',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user->id === auth()->id()) {
            return back()->withErrors(['email' => 'You cannot share with yourself']);
        }

        try {
            $item->shareWith($user, $validated['permission']);

            return back()->with('success', ucfirst($this->getModelName()).' shared successfully');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove a share for a resource.
     */
    public function unshare($id, int $userId)
    {
        $item = $this->model::findOrFail($id);
        $this->authorize('share', $item);

        $user = User::findOrFail($userId);

        try {
            $item->unshareWith($user);

            return back()->with('success', 'Share removed successfully');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to remove share');
        }
    }

    /**
     * Get shares for a resource (API endpoint).
     */
    public function getShares($id)
    {
        $item = $this->model::findOrFail($id);
        // Only owners can list shares for a resource
        $this->authorize('share', $item);

        $shares = $item->shares()->with('sharedWithUser:id,name,email')->get();

        return response()->json($shares);
    }

    /**
     * Display shared resources for the current user.
     */
    public function shared(Request $request)
    {
        $shareableType = $this->getShareableType();

        $query = $this->model::query()
            ->join('file_shares', function ($join) use ($shareableType) {
                $join->on($this->getFileIdColumn(), '=', 'file_shares.file_id')
                    ->where('file_shares.file_type', '=', $shareableType);
            })
            ->where('file_shares.shared_with_user_id', auth()->id())
            ->with(array_merge($this->indexWith ?? [], ['owner']))
            ->select($this->getTableName().'.*', 'file_shares.permission', 'file_shares.shared_at');

        // Apply search if provided
        if ($search = $request->input('search')) {
            $query = $this->applySearch($query, $search);
        }

        $items = $query->orderBy('file_shares.shared_at', 'desc')
            ->paginate($this->perPage ?? 20);

        return inertia("{$this->resource}/Shared", [
            'items' => $items->through(fn ($item) => $this->transformForSharedIndex($item)),
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Transform item for shared index display.
     */
    protected function transformForSharedIndex($item): array
    {
        $transformed = $this->transformForIndex($item);
        $transformed['owner'] = $item->owner?->only(['id', 'name', 'email']);
        $transformed['shared_permission'] = $item->permission;
        $transformed['shared_at'] = $item->shared_at;

        return $transformed;
    }

    /**
     * Get the shareable type for this controller's model.
     */
    abstract protected function getShareableType(): string;

    /**
     * Get the file ID column name for joining with file_shares.
     */
    protected function getFileIdColumn(): string
    {
        return $this->getTableName().'.file_id';
    }

    /**
     * Get the table name for the model.
     */
    protected function getTableName(): string
    {
        return (new $this->model)->getTable();
    }
}
