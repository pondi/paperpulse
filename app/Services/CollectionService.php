<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Collection as SupportCollection;

class CollectionService
{
    public function create(array $data, int $userId): Collection
    {
        return Collection::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? 'folder',
            'color' => $data['color'] ?? null,
        ]);
    }

    public function update(Collection $collection, array $data): Collection
    {
        $collection->update([
            'name' => $data['name'] ?? $collection->name,
            'description' => $data['description'] ?? $collection->description,
            'icon' => $data['icon'] ?? $collection->icon,
            'color' => $data['color'] ?? $collection->color,
        ]);

        return $collection->fresh();
    }

    public function delete(Collection $collection): bool
    {
        return $collection->delete();
    }

    public function archive(Collection $collection): Collection
    {
        $collection->update(['is_archived' => true]);

        return $collection->fresh();
    }

    public function unarchive(Collection $collection): Collection
    {
        $collection->update(['is_archived' => false]);

        return $collection->fresh();
    }

    /**
     * Add files to a collection.
     *
     * @param  array<int>  $fileIds
     */
    public function addFiles(Collection $collection, array $fileIds, ?int $userId = null): void
    {
        $userId = $userId ?? auth()->id();

        $validFileIds = File::where('user_id', $userId)
            ->whereIn('id', $fileIds)
            ->pluck('id');

        $collection->files()->syncWithoutDetaching($validFileIds);
    }

    /**
     * Remove files from a collection.
     *
     * @param  array<int>  $fileIds
     */
    public function removeFiles(Collection $collection, array $fileIds): void
    {
        $collection->files()->detach($fileIds);
    }

    /**
     * Sync files in a collection (replaces existing).
     *
     * @param  array<int>  $fileIds
     */
    public function syncFiles(Collection $collection, array $fileIds, ?int $userId = null): void
    {
        $userId = $userId ?? auth()->id();

        $validFileIds = File::where('user_id', $userId)
            ->whereIn('id', $fileIds)
            ->pluck('id');

        $collection->files()->sync($validFileIds);
    }

    public function getFilesCount(Collection $collection): int
    {
        return $collection->files()->count();
    }

    /**
     * Get all files in a collection.
     */
    public function getFiles(Collection $collection): SupportCollection
    {
        return $collection->files()
            ->with(['primaryReceipt', 'primaryDocument', 'primaryEntity'])
            ->get();
    }

    /**
     * Get collections for a user.
     */
    public function getCollectionsForUser(int $userId, bool $includeArchived = false): SupportCollection
    {
        $query = Collection::where('user_id', $userId)
            ->withCount('files')
            ->orderBy('name');

        if (! $includeArchived) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get active collections for dropdown/selector.
     */
    public function getActiveCollectionsForSelector(int $userId): SupportCollection
    {
        return Collection::where('user_id', $userId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color']);
    }

    /**
     * Find or create a collection by name.
     */
    public function findOrCreate(string $name, int $userId, ?string $icon = null, ?string $color = null): Collection
    {
        return Collection::findOrCreateByName($name, $userId, $icon, $color);
    }

    /**
     * Add a file to multiple collections.
     *
     * @param  array<int>  $collectionIds
     */
    public function addFileToCollections(File $file, array $collectionIds): void
    {
        $validCollectionIds = Collection::where('user_id', $file->user_id)
            ->whereIn('id', $collectionIds)
            ->pluck('id');

        $file->collections()->syncWithoutDetaching($validCollectionIds);
    }

    /**
     * Remove a file from all collections.
     */
    public function removeFileFromAllCollections(File $file): void
    {
        $file->collections()->detach();
    }

    /**
     * Get collections for a specific file.
     */
    public function getCollectionsForFile(File $file): SupportCollection
    {
        return $file->collections()->get();
    }

    /**
     * Calculate total amounts for files in a collection (for dashboard).
     *
     * @return array{total: float, count: int, by_type: array<string, array{count: int, total: float|int}>}
     */
    public function getCollectionStats(Collection $collection): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, File> $files */
        $files = $collection->files()
            ->with(['primaryReceipt', 'primaryDocument', 'primaryEntity.entity'])
            ->get();

        $totalAmount = 0;
        $byType = [
            'receipts' => ['count' => 0, 'total' => 0],
            'documents' => ['count' => 0, 'total' => 0],
            'invoices' => ['count' => 0, 'total' => 0],
            'contracts' => ['count' => 0, 'total' => 0],
        ];

        foreach ($files as $file) {
            /** @var File $file */
            $primaryReceipt = $file->primaryReceipt;
            $primaryDocument = $file->primaryDocument;
            $primaryEntity = $file->primaryEntity;

            if ($primaryReceipt) {
                $byType['receipts']['count']++;
                $amount = $primaryReceipt->total ?? 0;
                $byType['receipts']['total'] += $amount;
                $totalAmount += $amount;
            } elseif ($primaryDocument) {
                $byType['documents']['count']++;
            }

            if ($primaryEntity) {
                $entityType = $primaryEntity->entity_type;
                if ($entityType === 'App\\Models\\Invoice') {
                    $byType['invoices']['count']++;
                    $amount = $primaryEntity->entity->total ?? 0;
                    $byType['invoices']['total'] += $amount;
                    $totalAmount += $amount;
                } elseif ($entityType === 'App\\Models\\Contract') {
                    $byType['contracts']['count']++;
                }
            }
        }

        return [
            'total' => $totalAmount,
            'count' => $files->count(),
            'total_files' => $files->count(),
            'documents_count' => $byType['documents']['count'],
            'receipts_count' => $byType['receipts']['count'],
            'invoices_count' => $byType['invoices']['count'],
            'contracts_count' => $byType['contracts']['count'],
            'by_type' => $byType,
        ];
    }
}
