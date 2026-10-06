<?php

namespace App\Services\Files;

use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Services\Jobs\JobHistoryCreator;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileReviewService
{
    public function resolveTotals(File $file, int $reviewerId): void
    {
        $file->getConnection()->transaction(function () use ($file, $reviewerId): void {
            $locked = File::query()->lockForUpdate()->findOrFail($file->id);
            if ($locked->user_id !== $reviewerId) {
                throw new AuthorizationException('Only the owner may resolve this review.');
            }
            if ($locked->status !== 'needs_review' || ($locked->meta['review']['reason'] ?? null) !== 'receipt_totals') {
                throw ValidationException::withMessages(['review' => 'This file does not require receipt totals review.']);
            }
            $receipt = $locked->receipts()->where('user_id', $reviewerId)->with('lineItems')->first();
            $totals = $receipt?->totalsReconciliation();
            if (! $totals || $totals['needs_review'] || $totals['processed_items'] === 0 || $receipt->total_amount === null) {
                throw ValidationException::withMessages(['review' => 'The saved line items and source adjustments do not reconcile with the final amount. Correct the receipt details first, or retry extraction if source values are missing.']);
            }
            $meta = $locked->meta;
            $meta['review_resolution'] = ['reason' => 'receipt_totals', 'reviewed_by' => $reviewerId,
                'reviewed_at' => now()->toIso8601String(), 'reconciliation' => $totals];
            unset($meta['review']);
            $locked->update(['status' => 'completed', 'meta' => $meta]);
            $receipt->update(['receipt_data' => array_merge($receipt->sourceData(), ['total_reconciliation' => $totals])]);
        });
    }

    public function resolve(File $file, string $type): void
    {
        $file->getConnection()->transaction(function () use ($file, $type): void {
            $locked = File::query()->lockForUpdate()->findOrFail($file->id);
            if ($locked->status !== 'needs_review' || ($locked->meta['review']['reason'] ?? null) !== 'uncertain_classification') {
                throw ValidationException::withMessages(['file_type' => 'This file requires review of its processing limits or totals before retrying.']);
            }
            $jobId = (string) Str::uuid();
            $meta = $locked->meta ?? [];
            $meta['processing_generation'] = $jobId;
            $meta['review']['corrected_type'] = $type;
            $meta['review']['corrected_at'] = now()->toIso8601String();
            $locked->update(['meta' => $meta, 'status' => 'pending', 'file_type' => $type]);
            $metadata = ['fileId' => $locked->id, 'fileGuid' => $locked->guid, 'fileExtension' => $locked->fileExtension,
                'fileType' => $type, 'userId' => $locked->user_id, 's3OriginalPath' => $locked->s3_original_path,
                's3ArchivePath' => $locked->s3_archive_path, 'processingGeneration' => $jobId, 'targetedExtraction' => true,
                'jobName' => 'Resolve file review'];
            JobHistoryCreator::createParentJob($jobId, 'Resolve file review', $type, $metadata, $locked->id, $locked->fileName);
            JobMetadataPersistence::store($jobId, $metadata);
            ProcessFileGemini::dispatch($jobId)->onQueue('files')->afterCommit();
        });
    }
}
