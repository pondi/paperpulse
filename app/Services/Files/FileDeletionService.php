<?php

namespace App\Services\Files;

use App\Enums\DeletedReason;
use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileDeletionService
{
    /** @var list<class-string<Model>> */
    public const ENTITY_CLASSES = [Receipt::class, Document::class, Invoice::class, BankStatement::class, Contract::class, Voucher::class, Warranty::class, ReturnPolicy::class];

    public function deleteFile(File $source, int $ownerId, DeletedReason $reason = DeletedReason::UserDelete): void
    {
        $source->getConnection()->transaction(function () use ($source, $ownerId, $reason): void {
            $file = File::withoutGlobalScope('user')->withTrashed()
                ->where('user_id', $ownerId)->lockForUpdate()->find($source->id);
            if (! $file) {
                throw new AuthorizationException('Only the owner may delete this file.');
            }
            if ($file->trashed() && FileCleanupManifest::query()->where('file_id', $file->id)->exists()) {
                return;
            }

            $searchRecords = [];
            $restoreRecords = [];
            foreach (self::ENTITY_CLASSES as $entityClass) {
                foreach ($entityClass::withoutGlobalScope('user')->where('user_id', $ownerId)
                    ->where('file_id', $file->id)->get() as $entity) {
                    $this->collectRestoreRecords($entity, $restoreRecords);
                    $searchRecords = array_merge($searchRecords, $this->softDeleteEntity($entity, $reason));
                }
            }
            foreach ($file->extractableEntities()->where('user_id', $ownerId)->get() as $junction) {
                $restoreRecords[] = ['type' => $junction::class, 'id' => $junction->id];
                $this->softDelete($junction, $reason);
            }
            $meta = $file->meta ?? [];
            $meta['lifecycle']['deleted_records'] = $restoreRecords;
            $file->meta = $meta;
            $this->softDelete($file, $reason);
            $this->recordCleanup($file, $reason, $searchRecords, true);
        });
    }

    public function restoreFile(File $source, int $ownerId): File
    {
        return $source->getConnection()->transaction(function () use ($source, $ownerId): File {
            $file = File::withoutGlobalScope('user')->withTrashed()
                ->where('user_id', $ownerId)->lockForUpdate()->find($source->id);
            if (! $file) {
                throw new AuthorizationException('Only the owner may restore this file.');
            }
            $manifest = FileCleanupManifest::query()->where('file_id', $file->id)->lockForUpdate()->first();
            if ($manifest && collect($manifest->objects)->contains(fn (array $object): bool => $object['done'])) {
                throw ValidationException::withMessages(['file' => 'This file cannot be restored after asset cleanup has begun.']);
            }
            foreach ($file->meta['lifecycle']['deleted_records'] ?? [] as $record) {
                $model = $record['type']::withoutGlobalScope('user')->withTrashed()->find($record['id']);
                if ($model && $model->trashed() && $model->deleted_reason === $file->deleted_reason) {
                    $restore = function () use ($model): void {
                        $model->deleted_reason = null;
                        $model->restore();
                    };
                    if (method_exists($model, 'withoutSyncingToSearch')) {
                        $model::withoutSyncingToSearch($restore);
                    } else {
                        $restore();
                    }
                }
            }
            $file->deleted_reason = null;
            $file->restore();
            if ($manifest) {
                $manifest->update(['objects' => [], 'available_at' => null, 'completed_at' => null]);
            }

            return $file;
        });
    }

    public function backfillLegacyDeletions(): void
    {
        File::withoutGlobalScope('user')->onlyTrashed()->whereNull('deleted_reason')
            ->whereIn('status', ['completed', 'failed'])->chunkById(100, function ($files): void {
                foreach ($files as $file) {
                    if (! data_get($file->meta, 'reprocessing') && ! data_get($file->meta, 'reprocess')) {
                        $this->deleteFile($file, $file->user_id);
                    }
                }
            });
    }

    /** @param list<array{type: class-string<Model>, id: int}> $records */
    protected function collectRestoreRecords(Model $entity, array &$records): void
    {
        $records[] = ['type' => $entity::class, 'id' => $entity->id];
        $relation = match (true) {
            $entity instanceof Receipt, $entity instanceof Invoice => 'lineItems',
            $entity instanceof BankStatement => 'transactions',
            default => null,
        };
        if ($relation) {
            foreach ($entity->{$relation}()->get() as $child) {
                $this->collectRestoreRecords($child, $records);
            }
        }
    }

    public function deleteEntity(Model $entity, int $ownerId, DeletedReason $reason = DeletedReason::UserDelete): void
    {
        if ((int) $entity->user_id !== $ownerId) {
            throw new AuthorizationException('Only the owner may delete an extracted entity.');
        }

        $entity->getConnection()->transaction(function () use ($entity, $ownerId, $reason): void {
            $file = File::withoutGlobalScope('user')->withTrashed()
                ->where('user_id', $ownerId)->lockForUpdate()->find($entity->file_id);
            if (! $file) {
                throw new AuthorizationException('The source file is not owned by this user.');
            }

            $entity = $entity::withoutGlobalScope('user')->withTrashed()
                ->where('user_id', $ownerId)->lockForUpdate()->findOrFail($entity->id);
            if ($entity->trashed()) {
                return;
            }

            $searchRecords = $this->softDeleteEntity($entity, $reason);
            foreach (ExtractableEntity::query()->where('file_id', $file->id)
                ->where('user_id', $ownerId)->where('entity_id', $entity->id)
                ->whereIn('entity_type', [$entity->getMorphClass(), $entity::class])->get() as $junction) {
                $this->softDelete($junction, $reason);
            }

            $hasEntities = $file->extractableEntities()->exists();
            foreach (self::ENTITY_CLASSES as $entityClass) {
                $hasEntities = $hasEntities || $entityClass::withoutGlobalScope('user')
                    ->where('user_id', $ownerId)->where('file_id', $file->id)->exists();
            }
            if (! $hasEntities) {
                $this->softDelete($file, $reason);
            }
            $this->recordCleanup($file, $reason, $searchRecords, ! $hasEntities);
        });
    }

    /** @return list<array{type: class-string<Model>, id: int, done: bool}> */
    protected function softDeleteEntity(Model $entity, DeletedReason $reason): array
    {
        $records = [];
        $relation = match (true) {
            $entity instanceof Receipt, $entity instanceof Invoice => 'lineItems',
            $entity instanceof BankStatement => 'transactions',
            default => null,
        };
        if ($relation) {
            foreach ($entity->{$relation}()->get() as $child) {
                $records = array_merge($records, $this->softDeleteEntity($child, $reason));
            }
        }
        if (method_exists($entity, 'unsearchable')) {
            $records[] = ['type' => $entity::class, 'id' => $entity->id, 'done' => false];
        }
        $this->softDelete($entity, $reason);

        return $records;
    }

    protected function softDelete(Model $model, DeletedReason $reason): void
    {
        $delete = function () use ($model, $reason): void {
            if ($model instanceof File) {
                $meta = $model->meta ?? [];
                $meta['processing_generation'] = (string) Str::uuid();
                $model->meta = $meta;
                $model->shares()->delete();
            }
            $model->deleted_reason = $reason;
            $model->save();
            if (! $model->trashed()) {
                $model->delete();
            }
        };
        if (method_exists($model, 'withoutSyncingToSearch')) {
            $model::withoutSyncingToSearch($delete);
        } else {
            $delete();
        }
    }

    /** @param list<array{type: class-string<Model>, id: int, done: bool}> $searchRecords */
    protected function recordCleanup(File $file, DeletedReason $reason, array $searchRecords, bool $deleteObjects): FileCleanupManifest
    {
        $manifest = FileCleanupManifest::query()->firstOrNew(['file_id' => $file->id]);
        $objects = $manifest->objects ?? [];
        if ($deleteObjects) {
            $paths = array_filter([$file->s3_original_path, $file->s3_processed_path, $file->s3_archive_path, $file->s3_image_path, $file->file_path]);
            if ($file->guid) {
                $paths[] = StoragePathBuilder::storagePath($file->user_id, $file->guid, $file->file_type ?? 'document', 'original', $file->fileExtension ?? 'pdf');
            }
            foreach (array_unique($paths) as $path) {
                $objects[$path] ??= ['path' => $path, 'done' => false];
            }
        }
        $manifest->fill([
            'user_id' => $file->user_id,
            'reason' => $reason->value,
            'objects' => $objects,
            'search_records' => array_merge($manifest->search_records ?? [], $searchRecords),
            'available_at' => $deleteObjects ? now()->addDays(30) : null,
            'completed_at' => null,
        ])->save();

        return $manifest;
    }
}
