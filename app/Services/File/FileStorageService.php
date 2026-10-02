<?php

namespace App\Services\File;

use App\Contracts\Services\FileStorageContract;
use App\Models\FileProcessingRequest;
use App\Models\JobHistory;
use App\Services\S3StorageService;
use App\Services\StorageService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File as LocalFiles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService implements FileStorageContract
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Store uploaded file locally for processing
     */
    public function storeWorkingFile(UploadedFile $uploadedFile, string $fileGuid): string
    {
        return $this->storeWorkingContent($uploadedFile->getContent(), $fileGuid, $uploadedFile->getClientOriginalExtension());
    }

    /**
     * Store file content locally for processing
     */
    public function storeWorkingContent(string $content, string $fileGuid, string $extension): string
    {
        try {
            $directory = Storage::disk('local')->path('uploads/'.$fileGuid);
            LocalFiles::ensureDirectoryExists($directory, 0700);
            $path = 'uploads/'.$fileGuid.'/source.'.strtolower($extension);

            if (! Storage::disk('local')->put($path, $content)) {
                throw new Exception('Failed to write the working file');
            }

            chmod(Storage::disk('local')->path($path), 0600);
            Log::debug('[FileStorageService] Working content stored', [
                'file_path' => $path,
                'file_guid' => $fileGuid,
            ]);

            return Storage::disk('local')->path($path);
        } catch (Exception $e) {
            Log::error('[FileStorageService] Working content storage failed', [
                'error' => $e->getMessage(),
                'file_guid' => $fileGuid,
            ]);
            throw $e;
        }
    }

    /**
     * Store file to S3 storage bucket
     */
    public function storeToS3(string $content, int $userId, string $fileGuid, string $fileType, string $variant, string $extension): string
    {
        return $this->storageService->storeFile(
            $content,
            $userId,
            $fileGuid,
            $fileType,
            $variant,
            $extension
        );
    }

    /**
     * Store uploaded file to S3 storage bucket
     */
    public function storeUploadedFileToS3(UploadedFile $uploadedFile, int $userId, string $fileGuid, string $fileType, string $variant): string
    {
        $content = file_get_contents($uploadedFile->getRealPath());
        if ($content === false) {
            throw new Exception('Failed to read the uploaded file');
        }
        $extension = $uploadedFile->getClientOriginalExtension();

        return $this->storeToS3($content, $userId, $fileGuid, $fileType, $variant, $extension);
    }

    /**
     * Delete working file from local storage
     */
    public function deleteWorkingFile(string $filePath): bool
    {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);

                Log::debug('[FileStorageService] Working file deleted', [
                    'file_path' => $filePath,
                ]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('[FileStorageService] Working file deletion failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);

            return false;
        }
    }

    /**
     * Check if file exists in S3
     */
    public function existsInS3(string $disk, string $path): bool
    {
        return S3StorageService::exists($disk, $path);
    }

    /**
     * Get file content from S3
     */
    public function getFromS3(string $disk, string $path): string
    {
        return S3StorageService::get($disk, $path);
    }

    /**
     * Get file size from S3
     */
    public function getSizeFromS3(string $disk, string $path): int
    {
        return S3StorageService::size($disk, $path);
    }

    /**
     * Delete file from S3
     */
    public function deleteFromS3(string $disk, string $path): void
    {
        S3StorageService::delete($disk, $path);
    }

    /**
     * Generate unique file GUID
     */
    public function generateFileGuid(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Clean up temporary files older than specified hours
     */
    public function cleanupOldWorkingFiles(int $hoursOld = 24): int
    {
        $disk = Storage::disk('local');
        $active = JobHistory::query()->whereNull('parent_uuid')->whereIn('status', ['pending', 'queued', 'processing', 'retrying'])->get(['uuid', 'metadata']);
        $activeIds = $active->pluck('uuid')->merge(FileProcessingRequest::query()->whereIn('state', ['upload_pending', 'pending', 'dispatching'])->pluck('job_id'))->flip();
        $activeGuids = $active->pluck('metadata.fileGuid')->filter()->flip();
        $cutoff = time() - $hoursOld * 3600;
        $count = 0;
        foreach ($disk->directories('uploads') as $directory) {
            if ($activeIds->has(basename($directory))) {
                continue;
            }
            $files = $disk->allFiles($directory);
            if (collect($files)->every(fn (string $path): bool => $disk->lastModified($path) < $cutoff) && $disk->deleteDirectory($directory)) {
                $count += count($files);
            }
        }
        foreach ($disk->files('uploads') as $path) {
            if (! $activeGuids->has(pathinfo($path, PATHINFO_FILENAME)) && $disk->lastModified($path) < $cutoff && $disk->delete($path)) {
                $count++;
            }
        }

        return $count;
    }

    public function cleanupJob(string $jobId): void
    {
        $disk = Storage::disk('local');
        $disk->deleteDirectory('uploads/'.$jobId);
        $guid = JobHistory::query()->where('uuid', $jobId)->value('metadata')['fileGuid'] ?? null;
        if ($guid !== null && ! JobHistory::query()->whereNull('parent_uuid')->where('uuid', '!=', $jobId)
            ->where('metadata->fileGuid', $guid)->whereIn('status', ['pending', 'queued', 'processing', 'retrying'])->exists()) {
            foreach ($disk->files('uploads') as $path) {
                if (pathinfo($path, PATHINFO_FILENAME) === $guid) {
                    $disk->delete($path);
                }
            }
        }
    }
}
