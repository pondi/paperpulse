<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\File;
use App\Models\User;
use App\Support\AuthorizedEntityRelations;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

class CollectionService
{
    public function create(array $data, int $userId): Collection
    {
        $folder = app(FolderTreeService::class)->ensureFolder($userId, $data['name'], $data['parent_id'] ?? null);

        return app(FolderTreeService::class)->update($folder, $data);
    }

    public function update(Collection $collection, array $data): Collection
    {
        return app(FolderTreeService::class)->update($collection, $data);
    }

    public function delete(Collection $collection): bool
    {
        return app(FolderTreeService::class)->deleteLeaf($collection);
    }

    public function archive(Collection $collection): Collection
    {
        return app(FolderTreeService::class)->archiveTree($collection);
    }

    public function unarchive(Collection $collection): Collection
    {
        return app(FolderTreeService::class)->archiveTree($collection, false);
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
        $collection->getConnection()->transaction(function () use ($collection, $fileIds): void {
            $files = File::query()->where('user_id', $collection->user_id)->whereIn('id', $fileIds)->lockForUpdate()->get();
            if ($files->count() !== count(array_unique($fileIds))) {
                throw ValidationException::withMessages(['file_ids' => 'Select files owned by the collection owner.']);
            }
            foreach ($files as $file) {
                if ($file->primary_folder_id === $collection->id) {
                    $file->update(['primary_folder_id' => null, 'placement_source' => null,
                        'placement_version' => $file->placement_version + 1]);
                }
            }
            $collection->files()->detach($fileIds);
        });
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
        $collections = Collection::where('user_id', $userId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'icon', 'color']);
        $byId = $collections->keyBy('id');

        return $collections->each(function (Collection $collection) use ($byId): void {
            $names = [$collection->name];
            $parentId = $collection->parent_id;
            while ($parentId !== null) {
                $parent = $byId->get($parentId);
                array_unshift($names, $parent->name);
                $parentId = $parent->parent_id;
            }
            $collection->setAttribute('path', implode(' → ', $names));
        });
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
        $files = $collection->files()->withoutGlobalScope('user')
            ->with(['primaryEntity.entity' => fn ($query) => AuthorizedEntityRelations::load($query)])
            ->get();

        $currency = auth()->user()?->preference('currency', 'NOK') ?? $collection->user->preference('currency', 'NOK');
        $byType = [
            'receipts' => ['count' => 0, 'total' => 0.0], 'documents' => ['count' => 0, 'total' => 0.0],
            'invoices' => ['count' => 0, 'total' => 0.0], 'contracts' => ['count' => 0, 'total' => 0.0],
        ];
        $totalAmount = 0.0;
        $conversion = app(HistoricalCurrencyService::class);
        foreach ($files as $file) {
            $entity = $file->primaryEntity?->entity;
            $type = $file->primaryEntity?->entity_type;
            $key = ['receipt' => 'receipts', 'document' => 'documents', 'invoice' => 'invoices', 'contract' => 'contracts'][$type] ?? null;
            if (! $entity || ! $key) {
                continue;
            }
            $byType[$key]['count']++;
            if (in_array($type, ['receipt', 'invoice'], true)) {
                $amount = $conversion->convert($entity->total_amount === null ? null : (float) $entity->total_amount,
                    $entity->currency, $entity->getAttribute($type.'_date'), $currency);
                $byType[$key]['total'] = MonetarySummaryService::add($byType[$key]['total'], $amount);
                $totalAmount = MonetarySummaryService::add($totalAmount, $amount);
            }
        }

        return [
            'total' => $totalAmount,
            'currency' => $currency,
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
