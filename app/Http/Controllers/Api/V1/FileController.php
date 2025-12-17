<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DuplicateFileException;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\StoreFileRequest;
use App\Http\Resources\Api\V1\FileResource;
use App\Models\File;
use App\Services\FileProcessingService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class FileController extends BaseApiController
{
    public function index(Request $request)
    {
        $files = File::where('user_id', $request->user()->id)
            ->latest('uploaded_at')
            ->latest('created_at')
            ->paginate($request->per_page ?? 15);

        return $this->paginated(FileResource::collection($files));
    }

    public function show(Request $request, File $file, StorageService $storageService)
    {
        if ($file->user_id !== $request->user()->id) {
            return $this->error('File not found', 404);
        }

        $path = $file->s3_original_path;
        $url = $path ? $storageService->getTemporaryUrl($path, 60) : null;

        if (! $url) {
            return $this->error('File not available for download', 404);
        }

        return $this->success([
            'file' => new FileResource($file),
            'download_url' => $url,
            'expires_in_minutes' => 60,
        ], 'File URL generated successfully');
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
