<?php

declare(strict_types=1);

namespace App\Services\BulkUpload;

use App\Enums\BulkUploadFileStatus;
use App\Enums\BulkUploadSessionStatus;
use App\Models\BulkUploadFile;
use App\Models\BulkUploadSession;
use App\Models\File;
use App\Services\FileProcessingService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orchestrates bulk upload sessions: creation, file tracking,
 * confirmation, and integration with the existing processing pipeline.
 */
class BulkUploadService
{
    public function __construct(
        private FileProcessingService $fileProcessingService,
        private BulkPresignService $presignService,
    ) {}

    /**
     * Create a new bulk upload session with file manifest.
     *
     * @param  array{file_type: string, collection_ids?: array, tag_ids?: array, note?: string}  $defaults
     * @param  array<array{filename: string, path?: string, size: int, hash: string, extension: string, mime_type: string, file_type?: string, collection_ids?: array, tag_ids?: array, note?: string}>  $files
     * @return array{session: BulkUploadSession, files: \Illuminate\Support\Collection}
     */
    public function createSession(int $userId, array $defaults, array $files): array
    {
        $sessionUuid = (string) Str::uuid();

        return DB::transaction(function () use ($userId, $sessionUuid, $defaults, $files) {
            $session = BulkUploadSession::create([
                'uuid' => $sessionUuid,
                'user_id' => $userId,
                'status' => BulkUploadSessionStatus::Pending,
                'total_files' => count($files),
                'default_file_type' => $defaults['file_type'],
                'default_collection_ids' => $defaults['collection_ids'] ?? null,
                'default_tag_ids' => $defaults['tag_ids'] ?? null,
                'default_note' => $defaults['note'] ?? null,
                'expires_at' => now()->addHours(24),
            ]);

            // Normalize all hashes upfront
            $normalizedFiles = array_map(function (array $fileData): array {
                $fileData['_normalized_hash'] = $this->normalizeHash($fileData['hash']);

                return $fileData;
            }, $files);

            // Batch dedup check: single query for all hashes instead of N queries
            $allHashes = array_column($normalizedFiles, '_normalized_hash');
            $existingFiles = File::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->whereIn('file_hash', $allHashes)
                ->whereIn('status', ['completed', 'processing', 'pending'])
                ->get()
                ->keyBy('file_hash');

            // Build all rows for batch insert
            $now = now();
            $duplicateCount = 0;
            $insertRows = [];
            $fileUuids = [];

            foreach ($normalizedFiles as $fileData) {
                $fileUuid = (string) Str::uuid();
                $hash = $fileData['_normalized_hash'];
                $existingFile = $existingFiles->get($hash);
                $isDuplicate = $existingFile !== null;

                if ($isDuplicate) {
                    $duplicateCount++;
                }

                $fileUuids[] = $fileUuid;
                $insertRows[] = [
                    'uuid' => $fileUuid,
                    'bulk_upload_session_id' => $session->id,
                    'user_id' => $userId,
                    'original_filename' => $fileData['filename'],
                    'original_path' => $fileData['path'] ?? null,
                    'file_size' => $fileData['size'],
                    'file_hash' => $hash,
                    'file_extension' => strtolower($fileData['extension']),
                    'mime_type' => $fileData['mime_type'],
                    'status' => $isDuplicate ? BulkUploadFileStatus::Duplicate->value : BulkUploadFileStatus::Pending->value,
                    'file_type' => $fileData['file_type'] ?? null,
                    'collection_ids' => isset($fileData['collection_ids']) ? json_encode($fileData['collection_ids']) : null,
                    'tag_ids' => isset($fileData['tag_ids']) ? json_encode($fileData['tag_ids']) : null,
                    'note' => $fileData['note'] ?? null,
                    's3_key' => $this->buildS3Key($userId, $sessionUuid, $fileUuid, $fileData['extension']),
                    'presigned_expires_at' => null,
                    'file_id' => null,
                    'job_id' => null,
                    'error_message' => $isDuplicate
                        ? "Duplicate of file ID {$existingFile->id} (guid: {$existingFile->guid})"
                        : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Batch insert in chunks of 500 to avoid query size limits
            foreach (array_chunk($insertRows, 500) as $chunk) {
                BulkUploadFile::insert($chunk);
            }

            $session->update(['duplicate_count' => $duplicateCount]);

            // Fetch the created records for the response
            $bulkFiles = BulkUploadFile::where('bulk_upload_session_id', $session->id)
                ->whereIn('uuid', $fileUuids)
                ->get();

            Log::info('[BulkUpload] Session created', [
                'session_uuid' => $sessionUuid,
                'user_id' => $userId,
                'total_files' => count($files),
                'duplicates' => $duplicateCount,
            ]);

            return ['session' => $session->fresh(), 'files' => $bulkFiles];
        });
    }

    /**
     * Generate presigned PUT URLs for a batch of files.
     *
     * @param  array<string>  $fileUuids
     * @return array<array{uuid: string, url: string, expires_at: string, headers: array}>
     */
    public function presignFiles(BulkUploadSession $session, array $fileUuids): array
    {
        $this->ensureSessionActive($session);

        $files = $session->files()
            ->whereIn('uuid', $fileUuids)
            ->get();

        $presigned = [];

        /** @var BulkUploadFile $file */
        foreach ($files as $file) {
            if (! $file->status->canPresign()) {
                Log::warning('[BulkUpload] File cannot be presigned', [
                    'file_uuid' => $file->uuid,
                    'status' => $file->status->value,
                ]);

                continue;
            }

            $result = $this->presignService->generatePutUrl(
                $file->s3_key,
                $file->mime_type,
            );

            $file->update([
                'status' => BulkUploadFileStatus::Presigned,
                'presigned_expires_at' => $result['expires_at'],
            ]);

            $presigned[] = [
                'uuid' => $file->uuid,
                'url' => $result['url'],
                'expires_at' => $result['expires_at']->toIso8601String(),
                'headers' => $result['headers'],
            ];
        }

        // Update session status on first presign activity
        if ($session->status === BulkUploadSessionStatus::Pending) {
            $session->update(['status' => BulkUploadSessionStatus::Uploading]);
        }

        // Extend session expiry on activity
        $session->extendExpiry();

        return $presigned;
    }

    /**
     * Generate a fresh presigned URL for a single file (retry/expired).
     *
     * @return array{uuid: string, url: string, expires_at: string, headers: array}
     */
    public function presignSingleFile(BulkUploadSession $session, BulkUploadFile $file): array
    {
        $this->ensureSessionActive($session);

        if (! $file->status->canPresign()) {
            throw new Exception("File {$file->uuid} cannot be presigned in status {$file->status->value}");
        }

        $result = $this->presignService->generatePutUrl(
            $file->s3_key,
            $file->mime_type,
        );

        $file->update([
            'status' => BulkUploadFileStatus::Presigned,
            'presigned_expires_at' => $result['expires_at'],
            'error_message' => null,
        ]);

        $session->extendExpiry();

        return [
            'uuid' => $file->uuid,
            'url' => $result['url'],
            'expires_at' => $result['expires_at']->toIso8601String(),
            'headers' => $result['headers'],
        ];
    }

    /**
     * Confirm a file upload and trigger processing.
     *
     * Downloads from uplink-incoming, feeds into the standard FileProcessingService
     * pipeline, which handles S3 move, DB record, and job dispatch.
     *
     * @return array{file_id: int, file_guid: string, job_id: string}
     */
    public function confirmFile(BulkUploadSession $session, BulkUploadFile $bulkFile): array
    {
        $this->ensureSessionActive($session);

        // Idempotent: if already confirmed and processed, return existing data
        if ($bulkFile->file_id !== null && $bulkFile->status === BulkUploadFileStatus::Completed) {
            $file = File::withoutGlobalScopes()->find($bulkFile->file_id);

            return [
                'file_id' => $bulkFile->file_id,
                'file_guid' => $file->guid ?? '',
                'job_id' => $bulkFile->job_id ?? '',
            ];
        }

        // Also idempotent for processing state
        if ($bulkFile->file_id !== null && in_array($bulkFile->status, [
            BulkUploadFileStatus::Processing,
            BulkUploadFileStatus::Confirming,
        ])) {
            $file = File::withoutGlobalScopes()->find($bulkFile->file_id);

            return [
                'file_id' => $bulkFile->file_id,
                'file_guid' => $file->guid ?? '',
                'job_id' => $bulkFile->job_id ?? '',
            ];
        }

        if (! $bulkFile->status->canConfirm()) {
            throw new Exception("File {$bulkFile->uuid} cannot be confirmed in status {$bulkFile->status->value}");
        }

        $bulkFile->update(['status' => BulkUploadFileStatus::Confirming]);

        try {
            $disk = Storage::disk('uplink');
            if (! $disk->exists($bulkFile->s3_key)) {
                throw new Exception("File not found in S3 at {$bulkFile->s3_key}");
            }

            $fileContent = $disk->get($bulkFile->s3_key);
            $actualHash = hash('sha256', $fileContent);

            // Verify hash matches what client declared
            if ($actualHash !== $bulkFile->file_hash) {
                throw new Exception(
                    "Hash mismatch: expected {$bulkFile->file_hash}, got {$actualHash}"
                );
            }

            $fileData = [
                'content' => $fileContent,
                'fileName' => $bulkFile->original_filename,
                'extension' => $bulkFile->file_extension,
                'size' => strlen($fileContent),
                'mimeType' => $bulkFile->mime_type,
                'source' => 'uplink',
            ];

            $metadata = [
                'source' => 'uplink',
                'collection_ids' => $bulkFile->getEffectiveCollectionIds(),
                'tag_ids' => $bulkFile->getEffectiveTagIds(),
                'note' => $bulkFile->getEffectiveNote(),
                'bulkUploadSessionId' => $session->uuid,
                'bulkUploadFileId' => $bulkFile->uuid,
            ];

            $result = $this->fileProcessingService->processFile(
                $fileData,
                $bulkFile->getEffectiveFileType(),
                $session->user_id,
                $metadata,
            );

            $bulkFile->update([
                'status' => BulkUploadFileStatus::Processing,
                'file_id' => $result['fileId'],
                'job_id' => $result['jobId'],
                'error_message' => null,
            ]);

            // Clean up from uplink-incoming after successful handoff
            try {
                $disk->delete($bulkFile->s3_key);
            } catch (Exception $e) {
                Log::warning('[BulkUpload] Failed to clean up uplink-incoming file', [
                    's3_key' => $bulkFile->s3_key,
                    'error' => $e->getMessage(),
                ]);
            }

            $session->refreshCounts();
            $session->extendExpiry();

            Log::info('[BulkUpload] File confirmed and processing started', [
                'session_uuid' => $session->uuid,
                'file_uuid' => $bulkFile->uuid,
                'file_id' => $result['fileId'],
                'job_id' => $result['jobId'],
            ]);

            return [
                'file_id' => $result['fileId'],
                'file_guid' => $result['fileGuid'],
                'job_id' => $result['jobId'],
            ];
        } catch (Exception $e) {
            $bulkFile->update([
                'status' => BulkUploadFileStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            $session->refreshCounts();

            Log::error('[BulkUpload] File confirmation failed', [
                'session_uuid' => $session->uuid,
                'file_uuid' => $bulkFile->uuid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cancel a session and clean up unprocessed S3 files.
     */
    public function cancelSession(BulkUploadSession $session): void
    {
        if (! $session->isActive()) {
            throw new Exception('Session is not active and cannot be cancelled');
        }

        $session->update(['status' => BulkUploadSessionStatus::Cancelled]);

        // Clean up unprocessed files from S3
        $pendingFiles = $session->files()
            ->whereNotIn('status', [
                BulkUploadFileStatus::Completed,
                BulkUploadFileStatus::Processing,
                BulkUploadFileStatus::Duplicate,
            ])
            ->get();

        $disk = Storage::disk('uplink');

        /** @var BulkUploadFile $file */
        foreach ($pendingFiles as $file) {
            try {
                if ($file->s3_key && $disk->exists($file->s3_key)) {
                    $disk->delete($file->s3_key);
                }
            } catch (Exception $e) {
                Log::warning('[BulkUpload] Failed to clean up file during cancellation', [
                    's3_key' => $file->s3_key,
                    'error' => $e->getMessage(),
                ]);
            }

            $file->update(['status' => BulkUploadFileStatus::Skipped]);
        }

        $session->refreshCounts();

        Log::info('[BulkUpload] Session cancelled', [
            'session_uuid' => $session->uuid,
            'cleaned_files' => $pendingFiles->count(),
        ]);
    }

    /**
     * Get session with summary for status endpoint.
     *
     * @return array{session: BulkUploadSession, summary: array}
     */
    public function getSessionStatus(BulkUploadSession $session): array
    {
        $session->refreshCounts();

        $filesByStatus = $session->files()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'session' => $session,
            'summary' => [
                'total_files' => $session->total_files,
                'uploaded_count' => $session->uploaded_count,
                'completed_count' => $session->completed_count,
                'failed_count' => $session->failed_count,
                'duplicate_count' => $session->duplicate_count,
                'by_status' => $filesByStatus,
                'is_complete' => $session->completed_at !== null,
                'is_expired' => $session->isExpired(),
            ],
        ];
    }

    private function buildS3Key(int $userId, string $sessionUuid, string $fileUuid, string $extension): string
    {
        $prefix = config('filesystems.uplink_prefix', 'uplink-incoming/');

        return trim("{$prefix}{$userId}/{$sessionUuid}/{$fileUuid}.{$extension}", '/');
    }

    private function normalizeHash(string $hash): string
    {
        // Strip optional "sha256:" prefix
        if (str_starts_with($hash, 'sha256:')) {
            return substr($hash, 7);
        }

        return $hash;
    }

    private function ensureSessionActive(BulkUploadSession $session): void
    {
        if ($session->isExpired()) {
            throw new Exception('Upload session has expired');
        }

        if (! $session->isActive()) {
            throw new Exception("Session is not active (status: {$session->status->value})");
        }
    }
}
