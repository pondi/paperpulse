<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DuplicateFileException;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\StoreFileRequest;
use App\Http\Resources\Api\V1\FileDetailResource;
use App\Http\Resources\Api\V1\FileListResource;
use App\Models\File;
use App\Services\FileProcessingService;
use App\Services\Files\FileDetailService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class FileController extends BaseApiController
{
    /**
     * List files with optional filtering
     *
     * Single Responsibility: Validate filters and return paginated file list
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'file_type' => 'nullable|string|in:receipt,document',
            'status' => 'nullable|string|in:pending,processing,completed,failed',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = File::where('user_id', $request->user()->id);

        // Filter by file type (receipt or document)
        if (! empty($validated['file_type'])) {
            $query->where('file_type', $validated['file_type']);
        }

        // Filter by processing status
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $query->with($this->relationshipsForList($validated['file_type'] ?? null));

        $files = $query
            ->latest('uploaded_at')
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated(FileListResource::collection($files));
    }

    /**
     * @return array<int, string|array>
     */
    private function relationshipsForList(?string $fileType): array
    {
        // Use polymorphic primaryEntity for all file types
        return [
            'primaryEntity.entity' => function ($morphTo) {
                // Eager load relationships based on entity type
                $morphTo->morphWith([
                    \App\Models\Receipt::class => ['merchant', 'category'],
                    \App\Models\Document::class => ['category'],
                    \App\Models\Invoice::class => [],
                    \App\Models\Contract::class => [],
                ]);
            },
        ];
    }

    /**
     * Get detailed file information with receipt or document data
     *
     * Single Responsibility: Validate authorization and return detailed file data
     * - No business logic
     * - No S3 operations (use /files/{id}/content for file streaming)
     * - Delegates to FileDetailService for data retrieval
     */
    public function show(Request $request, int $file, FileDetailService $fileDetailService)
    {
        try {
            $fileWithDetails = $fileDetailService->getFileWithDetails($file, $request->user()->id);

            return $this->success(
                new FileDetailResource($fileWithDetails),
                'File details retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('File not found', 404);
        }
    }

    public function store(StoreFileRequest $request, FileProcessingService $fileProcessingService)
    {
        $fileType = $request->input('file_type', 'receipt');
        $uploadedFile = $request->file('file');

        try {
            $result = $fileProcessingService->processUpload(
                $uploadedFile,
                $fileType,
                $request->user()->id,
                [
                    'note' => $request->input('note'),
                    'collection_ids' => $request->input('collection_ids', []),
                    'tag_ids' => $request->input('tag_ids', []),
                ]
            );

            // Compute checksum for client-side confirmation
            $checksum = hash_file('sha256', $uploadedFile->getRealPath());

            Log::info('[API] File upload success', [
                'user_id' => $request->user()->id,
                'file_id' => $result['fileId'] ?? null,
                'file_guid' => $result['fileGuid'] ?? null,
                'job_id' => $result['jobId'] ?? null,
                'file_type' => $fileType,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'size' => $uploadedFile->getSize(),
                'mime' => $uploadedFile->getClientMimeType(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->success([
                'file_id' => $result['fileId'],
                'file_guid' => $result['fileGuid'],
                'job_id' => $result['jobId'],
                'job_name' => $result['jobName'],
                'file_type' => $fileType,
                'checksum_sha256' => $checksum,
            ], 'File uploaded for processing', 201);
        } catch (DuplicateFileException $e) {
            // Handle duplicate file gracefully - return 409 Conflict
            Log::info('[API] Duplicate file upload detected', [
                'user_id' => $request->user()->id,
                'file_type' => $fileType,
                'file_hash' => $e->getFileHash(),
                'existing_file_id' => $e->getExistingFile()->id,
            ]);

            return $this->error('Duplicate file detected', 409, [
                'duplicate' => $e->toArray(),
            ]);
        } catch (Throwable $e) {
            Log::error('[API] File upload failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'file_type' => $fileType,
            ]);

            return $this->error('File upload failed', 422, [
                'file' => [$e->getMessage()],
            ]);
        }
    }
}
