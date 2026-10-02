<?php

namespace App\Services\PulseDav;

use App\Jobs\PulseDav\ProcessPulseDavFile;
use App\Models\Document;
use App\Models\File;
use App\Models\FileProcessingRequest;
use App\Models\JobHistory;
use App\Models\PulseDavFile;
use App\Models\PulseDavImportBatch;
use App\Models\Receipt;
use App\Models\User;
use App\Services\PulseDav\Import\S3PathResolver;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportService
{
    public static function importFile(PulseDavFile $file, ?PulseDavImportBatch $batch, string $fileType, array $tagIds = [], ?string $note = null): bool
    {
        if (($batch !== null && $file->user_id !== $batch->user_id) || ! S3PathResolver::pathExists($file->s3_path, $file->user_id)) {
            throw ValidationException::withMessages(['s3_path' => 'Invalid scanner file for this batch.']);
        }

        return $file->getConnection()->transaction(function () use ($file, $batch, $fileType, $tagIds, $note): bool {
            User::query()->whereKey($file->user_id)->lockForUpdate()->firstOrFail();
            $file = PulseDavFile::query()->where('user_id', $file->user_id)->lockForUpdate()->findOrFail($file->id);
            self::reconcileFile($file);
            $file->refresh();
            if (! in_array($file->status, ['pending', 'failed'], true) || PulseDavFile::query()->where('user_id', $file->user_id)->where('s3_path', $file->s3_path)
                ->whereKeyNot($file->id)->whereIn('status', ['queued', 'processing', 'handed_off', 'completed'])->exists()) {
                return false;
            }
            $batch ??= PulseDavImportBatch::create(['user_id' => $file->user_id, 'imported_at' => now(), 'file_count' => 0, 'tag_ids' => $tagIds, 'notes' => $note]);
            $job = (new ProcessPulseDavFile($file, array_values(array_unique(array_merge($tagIds, $file->inherited_tags->pluck('id')->all()))), $note))
                ->onConnection('database')->onQueue('default');
            $file->update(['file_type' => $fileType, 'import_batch_id' => $batch->id, 'status' => 'queued',
                'job_id' => $job->jobID, 'claim_until' => now()->addSeconds($job->timeout + 60), 'file_id' => null,
                'processed_at' => null, 'error_message' => null, 'receipt_id' => null, 'document_id' => null]);
            JobHistory::create(['uuid' => $job->jobID, 'name' => 'Scanner Import', 'status' => 'pending', 'queue' => 'default',
                'metadata' => ['userId' => $file->user_id, 'metadata' => ['pulseDavFileId' => $file->id]]]);
            $batch->getConnection()->transaction(function () use ($batch): void {
                $locked = PulseDavImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
                $locked->update(['file_count' => $locked->files()->count()]);
            });
            $file->getConnection()->afterCommit(function () use ($job, $file): void {
                try {
                    dispatch($job);
                } catch (Throwable $exception) {
                    JobHistory::query()->where('uuid', $job->jobID)->update(['status' => 'failed', 'exception' => 'Scanner import dispatch failed.']);
                    $file->update(['status' => 'failed', 'error_message' => 'Scanner import dispatch failed.']);
                    throw $exception;
                }
            });

            return true;
        });
    }

    public static function reconcileJob(string $jobId): void
    {
        foreach (PulseDavFile::query()->where('job_id', $jobId)->whereIn('status', ['queued', 'processing', 'handed_off'])->get() as $source) {
            self::reconcileFile($source);
        }
    }

    public static function reconcileFile(PulseDavFile $source): void
    {
        $source->getConnection()->transaction(function () use ($source): void {
            $source = PulseDavFile::query()->lockForUpdate()->findOrFail($source->id);
            if (! in_array($source->status, ['queued', 'processing', 'handed_off'], true)) {
                return;
            }
            $request = FileProcessingRequest::query()->where('job_id', $source->job_id)->where('user_id', $source->user_id)->first();
            $fileId = $source->file_id ?? $request?->file_id;
            $job = JobHistory::query()->where('uuid', $source->job_id)->first();
            $file = $fileId ? File::withoutGlobalScope('user')->where('user_id', $source->user_id)->find($fileId) : null;
            if ($file !== null) {
                $source->file_id = $file->id;
                if (in_array($file->status, ['completed', 'needs_review'], true) && $file->extractableEntities()->exists()) {
                    $source->status = 'completed';
                    $source->processed_at = now();
                    $source->error_message = null;
                    $entity = $file->primaryEntity?->entity;
                    $source->receipt_id = $entity instanceof Receipt ? $entity->id : null;
                    $source->document_id = $entity instanceof Document ? $entity->id : null;
                } elseif ($file->status === 'failed' || in_array($job?->status, ['failed', 'cancelled'], true)) {
                    $source->status = 'failed';
                    $source->error_message = $file->meta['last_processing_error']['message'] ?? $job?->exception ?? 'Scanner extraction failed.';
                } else {
                    $source->status = $file->status === 'pending' ? 'handed_off' : 'processing';
                }
                $source->save();
            } elseif (in_array($job?->status, ['failed', 'cancelled'], true)) {
                $source->markAsFailed($job->exception ?? 'Scanner import failed.');
            } elseif (($source->claim_until ?? $source->updated_at->addSeconds(3660))->isPast()
                && ($job === null || $job->updated_at->lt(now()->subSeconds(3660)))
                && ! $source->getConnection()->table('jobs')->where('payload', 'like', '%'.($source->job_id ?? 'unlinked-source-'.$source->id).'%')->exists()) {
                $source->markAsFailed('Scanner import claim was abandoned.');
                if ($job !== null) {
                    $job->update(['status' => 'failed', 'exception' => 'Scanner import claim was abandoned.']);
                }
            }
            if ($source->import_batch_id !== null) {
                ScannerImportNotifier::notifyBatch($source->import_batch_id);
            }
        });
    }
}
