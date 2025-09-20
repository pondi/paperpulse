<?php

namespace App\Services\PulseDav;

use App\Contracts\Services\PulseDavFileContract;
use App\Models\PulseDavFile;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PulseDavFileService implements PulseDavFileContract
{
    protected $s3Client;

    protected $bucket;

    protected $incomingPrefix;

    public function __construct()
    {
        $this->s3Client = Storage::disk('pulsedav')->getClient();
        $this->bucket = config('filesystems.disks.pulsedav.bucket');
        $this->incomingPrefix = config('services.pulsedav.s3_incoming_prefix', 'incoming/');
    }

    /**
     * Download file from S3
     */
    public function downloadFile(string $s3Path): string
    {
        try {
            return Storage::disk('pulsedav')->get($s3Path);
        } catch (\Exception $e) {
            Log::error('[PulseDavFile] Failed to download S3 file', [
                's3_path' => $s3Path,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete file from S3 (soft delete with retention)
     */
    public function deleteFile(PulseDavFile $s3File): bool
    {
        try {
            // Soft delete in database first
            $s3File->delete();

            // Optionally move file to archive folder in S3
            $archivePath = 'archive/'.$s3File->s3_path;

            $this->s3Client->copyObject([
                'Bucket' => $this->bucket,
                'CopySource' => $this->bucket.'/'.$s3File->s3_path,
                'Key' => $archivePath,
            ]);

            // Delete original file
            Storage::disk('pulsedav')->delete($s3File->s3_path);

            Log::info('[PulseDavFile] File deleted and archived', [
                'file_id' => $s3File->id,
                's3_path' => $s3File->s3_path,
                'archive_path' => $archivePath,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[PulseDavFile] Failed to delete S3 file', [
                's3_file_id' => $s3File->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process multiple files
     */
    public function processFiles(array $fileIds, User $user, string $fileType = 'receipt'): int
    {
        $files = PulseDavFile::whereIn('id', $fileIds)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        $queued = 0;
        foreach ($files as $file) {
            // Update file type before processing
            $file->update(['file_type' => $fileType]);

            // Dispatch job to process this file
            \App\Jobs\PulseDav\ProcessPulseDavFile::dispatch($file);
            $file->markAsProcessing();
            $queued++;

            Log::info('[PulseDavFile] File queued for processing', [
                'file_id' => $file->id,
                'file_type' => $fileType,
            ]);
        }

        Log::info('[PulseDavFile] Files queued for processing', [
            'user_id' => $user->id,
            'queued_count' => $queued,
            'file_type' => $fileType,
        ]);

        return $queued;
    }

    /**
     * Generate a temporary download URL for a file
     */
    public function getTemporaryUrl(PulseDavFile $s3File, int $expiration = 60): string
    {
        try {
            $command = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $s3File->s3_path,
            ]);

            $request = $this->s3Client->createPresignedRequest($command, "+{$expiration} minutes");

            return (string) $request->getUri();
        } catch (\Exception $e) {
            Log::error('[PulseDavFile] Failed to generate temporary URL', [
                's3_file_id' => $s3File->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update file status
     */
    public function updateFileStatus(PulseDavFile $file, string $status, ?string $errorMessage = null): void
    {
        $updateData = ['status' => $status];

        if ($status === 'completed') {
            $updateData['processed_at'] = now();
        }

        if ($errorMessage) {
            $updateData['error_message'] = $errorMessage;
        }

        $file->update($updateData);

        Log::info('[PulseDavFile] File status updated', [
            'file_id' => $file->id,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark file as processing
     */
    public function markAsProcessing(PulseDavFile $file): void
    {
        $this->updateFileStatus($file, 'processing');
    }

    /**
     * Mark file as completed
     */
    public function markAsCompleted(PulseDavFile $file, ?int $receiptId = null, ?int $documentId = null): void
    {
        $updateData = [
            'status' => 'completed',
            'processed_at' => now(),
        ];

        if ($receiptId) {
            $updateData['receipt_id'] = $receiptId;
        }

        if ($documentId) {
            $updateData['document_id'] = $documentId;
        }

        $file->update($updateData);

        Log::info('[PulseDavFile] File marked as completed', [
            'file_id' => $file->id,
            'receipt_id' => $receiptId,
            'document_id' => $documentId,
        ]);
    }

    /**
     * Mark file as failed
     */
    public function markAsFailed(PulseDavFile $file, string $errorMessage): void
    {
        $this->updateFileStatus($file, 'failed', $errorMessage);
    }

    /**
     * Check if file exists in S3
     */
    public function existsInS3(string $s3Path): bool
    {
        try {
            return Storage::disk('pulsedav')->exists($s3Path);
        } catch (\Exception $e) {
            Log::error('[PulseDavFile] Failed to check file existence', [
                's3_path' => $s3Path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get file size in S3
     */
    public function getFileSize(string $s3Path): int
    {
        try {
            return Storage::disk('pulsedav')->size($s3Path);
        } catch (\Exception $e) {
            Log::error('[PulseDavFile] Failed to get file size', [
                's3_path' => $s3Path,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get file metadata
     */
    public function getFileMetadata(PulseDavFile $file): array
    {
        try {
            $metadata = [];

            if ($this->existsInS3($file->s3_path)) {
                $metadata['exists'] = true;
                $metadata['size'] = $this->getFileSize($file->s3_path);
                $metadata['extension'] = pathinfo($file->filename, PATHINFO_EXTENSION);
                $metadata['mime_type'] = $this->guessMimeType($file->filename);
            } else {
                $metadata['exists'] = false;
            }

            return $metadata;
        } catch (\Exception $e) {
            Log::error('[PulseDavFile] Failed to get file metadata', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);

            return ['exists' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Guess MIME type from filename
     */
    protected function guessMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
