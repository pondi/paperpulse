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

class FileDeletionService
{
    /** @var list<class-string<Model>> */
    public const ENTITY_CLASSES = [Receipt::class, Document::class, Invoice::class, BankStatement::class, Contract::class, Voucher::class, Warranty::class, ReturnPolicy::class];

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
            $model->deleted_reason = $reason;
            $model->save();
            $model->delete();
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
