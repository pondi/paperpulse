<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PublicShareAction;
use App\Models\Collection;
use App\Models\File;
use App\Models\PublicCollectionLink;
use App\Models\PublicShareAccessLog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PublicCollectionSharingService
{
    /**
     * Create a public link for a collection.
     *
     * @param  array{label?: string, is_password_protected?: bool, expires_at?: string|null, max_views?: int|null}  $options
     * @return array{link: PublicCollectionLink, password: string|null, url: string}
     */
    public function createLink(Collection $collection, array $options = []): array
    {
        $password = null;
        $passwordHash = null;

        if (! empty($options['is_password_protected'])) {
            $password = Str::random(8);
            $passwordHash = Hash::make($password);
        }

        $link = PublicCollectionLink::create([
            'collection_id' => $collection->id,
            'created_by_user_id' => auth()->id(),
            'label' => $options['label'] ?? null,
            'password_hash' => $passwordHash,
            'is_password_protected' => ! empty($options['is_password_protected']),
            'expires_at' => $options['expires_at'] ?? null,
            'max_views' => $options['max_views'] ?? null,
        ]);

        return [
            'link' => $link,
            'password' => $password,
            'url' => $this->buildPublicUrl($link),
        ];
    }

    public function revokeLink(PublicCollectionLink $link): void
    {
        $link->revoke();
    }

    public function findActiveLink(string $token): ?PublicCollectionLink
    {
        return PublicCollectionLink::where('token', $token)
            ->active()
            ->first();
    }

    public function findLinkByToken(string $token): ?PublicCollectionLink
    {
        return PublicCollectionLink::where('token', $token)->first();
    }

    public function verifyPassword(PublicCollectionLink $link, string $password): bool
    {
        return Hash::check($password, $link->password_hash);
    }

    public function logAccess(
        PublicCollectionLink $link,
        Request $request,
        PublicShareAction $action,
        ?array $metadata = null,
    ): void {
        PublicShareAccessLog::create([
            'public_collection_link_id' => $link->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'action' => $action,
            'metadata' => $metadata,
            'accessed_at' => now(),
        ]);
    }

    public function getAccessLogs(PublicCollectionLink $link, int $perPage = 20): LengthAwarePaginator
    {
        return $link->accessLogs()
            ->orderByDesc('accessed_at')
            ->paginate($perPage);
    }

    public function getActiveLinksForCollection(Collection $collection): SupportCollection
    {
        return PublicCollectionLink::where('collection_id', $collection->id)
            ->active()
            ->orderByDesc('created_at')
            ->get();
    }

    public function getAllLinksForCollection(Collection $collection): SupportCollection
    {
        return $collection->publicLinks()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Load collection + files with entities for public view, bypassing user scope.
     *
     * @return array{collection: Collection, files: SupportCollection}
     */
    public function getCollectionForPublicView(PublicCollectionLink $link): array
    {
        $collection = Collection::withoutGlobalScope('user')
            ->with(['user:id,name'])
            ->findOrFail($link->collection_id);

        $files = $collection->files()
            ->withoutGlobalScope('user')
            ->with([
                'extractableEntities.entity',
                'primaryEntity.entity',
                'tags',
            ])
            ->orderBy('fileName')
            ->get();

        return [
            'collection' => $collection,
            'files' => $files,
        ];
    }

    public function buildPublicUrl(PublicCollectionLink $link): string
    {
        return url('/shared/collections/'.$link->token);
    }

    /**
     * Deactivate expired links.
     */
    public function cleanupExpiredLinks(): int
    {
        return PublicCollectionLink::where('is_active', true)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now());
                })->orWhere(function ($q) {
                    $q->whereNotNull('max_views')
                        ->whereColumn('view_count', '>=', 'max_views');
                });
            })
            ->update(['is_active' => false]);
    }

    /**
     * Verify a file belongs to the shared collection.
     */
    public function fileExistsInCollection(PublicCollectionLink $link, string $fileGuid): ?File
    {
        return File::withoutGlobalScope('user')
            ->where('guid', $fileGuid)
            ->whereHas('collections', function ($query) use ($link) {
                $query->where('collections.id', $link->collection_id);
            })
            ->first();
    }
}
