<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\BulkPresignRequest;
use App\Http\Requests\Api\V1\CreateBulkSessionRequest;
use App\Models\BulkUploadFile;
use App\Models\BulkUploadSession;
use App\Services\BulkUpload\BulkUploadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BulkUploadController extends BaseApiController
{
    public function __construct(
        private BulkUploadService $bulkUploadService,
    ) {}

    /**
     * List user's bulk upload sessions.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $sessions = BulkUploadSession::where('user_id', $user->id)
            ->latest()
            ->paginate(15);

        return $this->paginated($sessions);
    }

    /**
     * Create a new bulk upload session with file manifest.
     */
    public function store(CreateBulkSessionRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $result = $this->bulkUploadService->createSession(
                $user->id,
                [
                    'file_type' => $request->input('file_type'),
                    'collection_ids' => $request->input('collection_ids'),
                    'tag_ids' => $request->input('tag_ids'),
                    'note' => $request->input('note'),
                ],
                $request->input('files'),
            );

            /** @var BulkUploadSession $session */
            $session = $result['session'];
            $files = $result['files'];

            $uploadableCount = $files->where('status', '!=', \App\Enums\BulkUploadFileStatus::Duplicate)->count();

            Log::info('[API] Bulk upload session created', [
                'user_id' => $user->id,
                'session_uuid' => $session->uuid,
                'total_files' => $session->total_files,
                'duplicates' => $session->duplicate_count,
            ]);

            return $this->success([
                'session_id' => $session->uuid,
                'status' => $session->status->value,
                'total_files' => $session->total_files,
                'duplicate_files' => $session->duplicate_count,
                'uploadable_files' => $uploadableCount,
                'expires_at' => $session->expires_at->toIso8601String(),
                'files' => $files->map(function (BulkUploadFile $f): array {
                    return [
                        'uuid' => $f->uuid,
                        'filename' => $f->original_filename,
                        'status' => $f->status->value,
                        'error_message' => $f->error_message,
                    ];
                }),
            ], 'Bulk upload session created', 201);
        } catch (Exception $e) {
            Log::error('[API] Bulk session creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return $this->error('Failed to create bulk upload session', 422);
        }
    }

    /**
     * Get session status with file progress.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $session = $this->findSession($request, $uuid);
        if (! $session) {
            return $this->notFound('Upload session not found');
        }

        $result = $this->bulkUploadService->getSessionStatus($session);
        /** @var BulkUploadSession $session */
        $session = $result['session'];

        return $this->success([
            'session_id' => $session->uuid,
            'status' => $session->status->value,
            'expires_at' => $session->expires_at->toIso8601String(),
            'completed_at' => $session->completed_at?->toIso8601String(),
            'summary' => $result['summary'],
            'files' => $session->files->map(function (BulkUploadFile $f): array {
                return [
                    'uuid' => $f->uuid,
                    'filename' => $f->original_filename,
                    'original_path' => $f->original_path,
                    'status' => $f->status->value,
                    'file_id' => $f->file_id,
                    'job_id' => $f->job_id,
                    'error_message' => $f->error_message,
                ];
            }),
        ]);
    }

    /**
     * Generate presigned PUT URLs for a batch of files.
     */
    public function presign(BulkPresignRequest $request, string $uuid): JsonResponse
    {
        $session = $this->findSession($request, $uuid);
        if (! $session) {
            return $this->notFound('Upload session not found');
        }

        try {
            $presigned = $this->bulkUploadService->presignFiles(
                $session,
                $request->input('file_uuids'),
            );

            return $this->success(['presigned' => $presigned]);
        } catch (Exception $e) {
            Log::error('[API] Bulk presign failed', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
            ]);

            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Generate a fresh presigned URL for a single file (retry/expired).
     */
    public function presignFile(Request $request, string $uuid, string $fileUuid): JsonResponse
    {
        $session = $this->findSession($request, $uuid);
        if (! $session) {
            return $this->notFound('Upload session not found');
        }

        /** @var BulkUploadFile|null $bulkFile */
        $bulkFile = $session->files()->where('uuid', $fileUuid)->first();
        if (! $bulkFile) {
            return $this->notFound('File not found in session');
        }

        try {
            $result = $this->bulkUploadService->presignSingleFile($session, $bulkFile);

            return $this->success($result);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Confirm a file was uploaded to S3 and trigger processing.
     */
    public function confirmFile(Request $request, string $uuid, string $fileUuid): JsonResponse
    {
        $session = $this->findSession($request, $uuid);
        if (! $session) {
            return $this->notFound('Upload session not found');
        }

        /** @var BulkUploadFile|null $bulkFile */
        $bulkFile = $session->files()->where('uuid', $fileUuid)->first();
        if (! $bulkFile) {
            return $this->notFound('File not found in session');
        }

        try {
            $result = $this->bulkUploadService->confirmFile($session, $bulkFile);

            return $this->success([
                'file_id' => $result['file_id'],
                'file_guid' => $result['file_guid'],
                'job_id' => $result['job_id'],
                'status' => 'processing',
            ], 'File confirmed and processing started');
        } catch (Exception $e) {
            Log::error('[API] File confirmation failed', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'file_uuid' => $fileUuid,
            ]);

            return $this->error('File confirmation failed: '.$e->getMessage(), 422);
        }
    }

    /**
     * Cancel a session and clean up unprocessed files.
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $session = $this->findSession($request, $uuid);
        if (! $session) {
            return $this->notFound('Upload session not found');
        }

        try {
            $this->bulkUploadService->cancelSession($session);

            return $this->success(null, 'Session cancelled');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    private function findSession(Request $request, string $uuid): ?BulkUploadSession
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return BulkUploadSession::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->first();
    }
}
