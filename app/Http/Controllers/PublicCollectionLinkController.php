<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreatePublicCollectionLinkRequest;
use App\Models\Collection;
use App\Models\PublicCollectionLink;
use App\Notifications\PublicCollectionSharedNotification;
use App\Services\PublicCollectionSharingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

class PublicCollectionLinkController extends Controller
{
    public function __construct(
        private readonly PublicCollectionSharingService $sharingService,
    ) {}

    public function store(CreatePublicCollectionLinkRequest $request, Collection $collection): RedirectResponse
    {
        $this->authorize('createPublicLink', $collection);

        $result = $this->sharingService->createLink($collection, [
            'label' => $request->validated('label'),
            'is_password_protected' => $request->boolean('is_password_protected'),
            'expires_at' => $request->resolvedExpiresAt(),
            'max_views' => $request->validated('max_views'),
        ]);

        if ($request->filled('notify_email')) {
            Notification::route('mail', $request->input('notify_email'))
                ->notify(new PublicCollectionSharedNotification(
                    collection: $collection,
                    createdBy: $request->user(),
                    url: $result['url'],
                    password: $result['password'],
                    expiresAt: $result['link']->expires_at,
                ));
        }

        return back()->with('publicLink', [
            'url' => $result['url'],
            'password' => $result['password'],
            'link_id' => $result['link']->id,
        ]);
    }

    public function destroy(Collection $collection, PublicCollectionLink $publicCollectionLink): RedirectResponse
    {
        $this->authorize('managePublicLink', $collection);
        abort_unless($publicCollectionLink->collection_id === $collection->id, 404);

        $this->sharingService->revokeLink($publicCollectionLink);

        return back()->with('success', 'Public link revoked.');
    }

    public function logs(Collection $collection, PublicCollectionLink $publicCollectionLink): JsonResponse
    {
        $this->authorize('managePublicLink', $collection);
        abort_unless($publicCollectionLink->collection_id === $collection->id, 404);

        $logs = $this->sharingService->getAccessLogs($publicCollectionLink);

        return response()->json($logs);
    }
}
