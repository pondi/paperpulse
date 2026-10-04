<?php

namespace App\Services\Files;

use App\Enums\DeletedReason;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Services\StorageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class FileCleanupService
{
    public function __construct(protected StorageService $storage) {}

    /** @return array{objects_deleted: int, failed: int} */
    public function process(FileCleanupManifest $source): array
    {
        return $source->getConnection()->transaction(function () use ($source): array {
            $file = File::withoutGlobalScope('user')->withTrashed()->lockForUpdate()->find($source->file_id);
            $manifest = FileCleanupManifest::query()->lockForUpdate()->findOrFail($source->id);
            $sourceOnly = $manifest->reason === DeletedReason::RetentionSource->value;
            $objectsDeleted = 0;
            $failed = 0;
            $search = $manifest->search_records;
            foreach ($search as $index => $record) {
                if ($record['done']) {
                    continue;
                }
                try {
                    $this->removeSearchRecord($record, $manifest->user_id);
                    $search[$index]['done'] = true;
                    $manifest->search_records = $search;
                    $manifest->save();
                } catch (Throwable $exception) {
                    $manifest->last_error = $exception->getMessage();
                    $failed++;
                }
            }
            $objects = $manifest->objects;
            $canDelete = ! $file || $file->trashed()
                || ($sourceOnly && File::withoutGlobalScope('user')->whereKey($file->id)->retainable(now())->exists());
            if ($canDelete && $manifest->available_at?->isPast()) {
                foreach ($objects as $key => $object) {
                    if ($object['done']) {
                        continue;
                    }
                    try {
                        $currentGeneration = ! $sourceOnly || ! $file
                            || ($object['generation'] ?? null) === ($file->meta['processing_generation'] ?? null);
                        if ($currentGeneration && ! $this->hasLiveReference($object['path'], $sourceOnly ? $file?->id : null)) {
                            $deleted = isset($object['disk'])
                                ? (! Storage::disk($object['disk'])->exists($object['path']) || Storage::disk($object['disk'])->delete($object['path']))
                                : $this->storage->deleteFile($object['path']);
                            if (! $deleted) {
                                throw new RuntimeException('Storage refused deletion: '.$object['path']);
                            }
                            $objectsDeleted++;
                        }
                        $objects[$key]['done'] = true;
                        $manifest->objects = $objects;
                        $manifest->save();
                        if ($sourceOnly && $file && $currentGeneration) {
                            foreach (['s3_original_path', 's3_processed_path', 's3_archive_path'] as $column) {
                                if ($file->{$column} === $object['path']) {
                                    $file->{$column} = null;
                                }
                            }
                            $file->saveQuietly();
                        }
                    } catch (Throwable $exception) {
                        $manifest->last_error = $exception->getMessage();
                        $failed++;
                    }
                }
            }
            $allDone = ! collect(array_merge(array_values($objects), $search))->contains(fn (array $record): bool => ! $record['done']);
            if ($sourceOnly && $file && collect($objects)->every(fn (array $object): bool => $object['done']
                && ($object['generation'] ?? null) === ($file->meta['processing_generation'] ?? null))) {
                $meta = $file->meta ?? [];
                $meta['retention'] = ['source_removed_at' => now()->toISOString(), 'generation' => $meta['processing_generation'] ?? null];
                $file->meta = $meta;
                $file->saveQuietly();
            }
            $manifest->completed_at = $allDone ? now() : null;
            if ($allDone) {
                $manifest->last_error = null;
            }
            $manifest->save();

            return ['objects_deleted' => $objectsDeleted, 'failed' => $failed];
        });
    }

    /** @param array{type: class-string<Model>, id: int, done: bool} $record */
    protected function removeSearchRecord(array $record, int $ownerId): void
    {
        $entity = $record['type']::withoutGlobalScope('user')->withTrashed()->find($record['id']);
        $exists = $entity !== null;
        if (! $entity) {
            $entity = new $record['type'];
            $entity->forceFill(['id' => $record['id'], 'user_id' => $ownerId]);
            $entity->exists = true;
        }
        if (isset($entity->user_id) && (int) $entity->user_id !== $ownerId) {
            throw new RuntimeException('Cleanup search owner does not match.');
        }
        if ($exists && ! $entity->trashed()) {
            $entity->searchableUsing()->update(new Collection([$entity]));
        } else {
            $entity->searchableUsing()->delete(new Collection([$entity]));
        }
    }

    protected function hasLiveReference(string $path, ?int $ignoredFileId = null): bool
    {
        return File::withoutGlobalScope('user')->when($ignoredFileId, fn ($query) => $query->whereKeyNot($ignoredFileId))
            ->where(function ($query) use ($path): void {
                foreach (['s3_original_path', 's3_processed_path', 's3_archive_path', 's3_image_path'] as $column) {
                    $query->orWhere($column, $path);
                }
                if (preg_match('~^(?:documents|receipts)/(\d+)/([^/]+)/~', $path, $matches)) {
                    $query->orWhere(function ($query) use ($matches): void {
                        $query->where('user_id', (int) $matches[1])->where('guid', $matches[2]);
                    });
                }
            })->exists();
    }

    /** @return array<class-string<Model>, list<int>> */
    public function pendingSearchRecords(): array
    {
        $pending = [];
        FileCleanupManifest::query()->whereNull('completed_at')->each(function (FileCleanupManifest $manifest) use (&$pending): void {
            foreach ($manifest->search_records as $record) {
                if (! $record['done']) {
                    $pending[$record['type']][] = $record['id'];
                }
            }
        });

        return $pending;
    }
}
