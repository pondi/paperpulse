<?php

namespace App\Services\Files;

use App\Enums\DeletedReason;
use App\Models\BulkUploadFile;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\JobHistory;
use App\Models\PulseDavFile;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Carbon;

class AccountDeletionService
{
    public function __construct(protected FileDeletionService $deletion) {}

    public function prepare(User $user): void
    {
        $user->tokens()->delete();
        $user->getConnection()->table('sessions')->where('user_id', $user->id)->delete();

        File::withoutGlobalScope('user')->withTrashed()->where('user_id', $user->id)
            ->chunkById(100, function ($files) use ($user): void {
                foreach ($files as $file) {
                    $search = [];
                    foreach (FileDeletionService::ENTITY_CLASSES as $entityClass) {
                        $entityClass::withoutGlobalScope('user')->withTrashed()->where('user_id', $user->id)
                            ->where('file_id', $file->id)->each(function ($entity) use (&$search): void {
                                if (method_exists($entity, 'unsearchable')) {
                                    $search[] = ['type' => $entity::class, 'id' => $entity->id, 'done' => false];
                                }
                                if ($entity instanceof Receipt) {
                                    $entity->lineItems()->withTrashed()->each(function ($item) use (&$search): void {
                                        $search[] = ['type' => $item::class, 'id' => $item->id, 'done' => false];
                                    });
                                }
                            });
                    }
                    $this->deletion->deleteFile($file, $user->id, DeletedReason::AccountDelete);
                    FileCleanupManifest::query()->where('file_id', $file->id)->firstOrFail()->update([
                        'reason' => DeletedReason::AccountDelete->value,
                        'search_records' => $search,
                        'available_at' => now(),
                        'completed_at' => null,
                    ]);
                    JobHistory::query()->where('file_id', $file->id)->each(fn (JobHistory $parent) => $this->cancel($parent));
                }
            });
        JobHistory::query()->where('metadata->userId', $user->id)->each(fn (JobHistory $parent) => $this->cancel($parent));

        PulseDavFile::withoutGlobalScope('user')->withTrashed()->where('user_id', $user->id)
            ->chunkById(100, function ($files) use ($user): void {
                $objects = [];
                foreach ($files as $file) {
                    foreach ([$file->s3_path, 'archive/'.$file->s3_path] as $path) {
                        $objects[] = ['disk' => config('filesystems.incoming_disk', 'pulsedav'), 'path' => $path, 'done' => false];
                    }
                }
                $this->recordObjects($user->id, $objects, now());
            });
        BulkUploadFile::withoutGlobalScope('user')->where('user_id', $user->id)->whereNotNull('s3_key')
            ->chunkById(100, function ($files) use ($user): void {
                $objects = [];
                $availableAt = now();
                foreach ($files as $file) {
                    $objects[] = ['disk' => 'uplink', 'path' => $file->s3_key, 'done' => false];
                    if ($file->presigned_expires_at?->greaterThan($availableAt)) {
                        $availableAt = $file->presigned_expires_at->copy()->addMinute();
                    }
                }
                $this->recordObjects($user->id, $objects, $availableAt);
            });
    }

    /** @param list<array{disk: string, path: string, done: bool}> $objects */
    protected function recordObjects(int $ownerId, array $objects, Carbon $availableAt): void
    {
        FileCleanupManifest::query()->create([
            'file_id' => null, 'user_id' => $ownerId, 'reason' => DeletedReason::AccountDelete->value,
            'objects' => $objects, 'search_records' => [], 'available_at' => $availableAt,
        ]);
    }

    protected function cancel(JobHistory $parent): void
    {
        JobHistory::query()->where(function ($query) use ($parent): void {
            $query->where('uuid', $parent->uuid)->orWhere('parent_uuid', $parent->uuid);
        })->whereNotIn('status', ['completed', 'failed', 'cancelled'])
            ->update(['status' => 'cancelled', 'finished_at' => now()]);
    }
}
