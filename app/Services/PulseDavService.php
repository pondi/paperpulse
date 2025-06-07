<?php

namespace App\Services;

use App\Models\PulseDavFile;
use App\Models\User;
use App\Notifications\ScannerFilesImported;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PulseDavService
{
    protected $s3Client;

    protected $bucket;

    protected $incomingPrefix;

    public function __construct()
    {
        $this->s3Client = Storage::disk('s3')->getClient();
        $this->bucket = config('filesystems.disks.s3.bucket');
        $this->incomingPrefix = config('services.pulsedav.s3_incoming_prefix', 'incoming/');
    }

    /**
     * List all PulseDav files for a specific user from S3
     */
    public function listUserFiles(User $user)
    {
        $prefix = $this->incomingPrefix.$user->id.'/';

        try {
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ]);

            $files = [];
            if (isset($objects['Contents'])) {
                foreach ($objects['Contents'] as $object) {
                    // Skip directories
                    if (substr($object['Key'], -1) === '/') {
                        continue;
                    }

                    $files[] = [
                        's3_path' => $object['Key'],
                        'filename' => basename($object['Key']),
                        'size' => $object['Size'],
                        'uploaded_at' => $object['LastModified'],
                    ];
                }
            }

            return $files;
        } catch (\Exception $e) {
            Log::error('Failed to list S3 files', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Sync S3 files to database
     */
    public function syncS3Files(User $user)
    {
        $s3Files = $this->listUserFiles($user);
        $synced = 0;

        foreach ($s3Files as $fileData) {
            // Check if file already exists in database
            $exists = PulseDavFile::where('s3_path', $fileData['s3_path'])
                ->where('user_id', $user->id)
                ->exists();

            if (! $exists) {
                PulseDavFile::create([
                    'user_id' => $user->id,
                    's3_path' => $fileData['s3_path'],
                    'filename' => $fileData['filename'],
                    'size' => $fileData['size'],
                    'uploaded_at' => $fileData['uploaded_at'],
                    'status' => 'pending',
                ]);
                $synced++;
            }
        }

        // Send notification if files were synced
        if ($synced > 0 && $user->preferences) {
            if ($user->preferences->notify_scanner_import) {
                try {
                    $user->notify(new ScannerFilesImported($synced));
                } catch (\Exception $e) {
                    Log::warning('Failed to send scanner import notification', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $synced;
    }

    /**
     * Download file from S3
     */
    public function downloadFile($s3Path)
    {
        try {
            return Storage::disk('s3')->get($s3Path);
        } catch (\Exception $e) {
            Log::error('Failed to download S3 file', [
                's3_path' => $s3Path,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete file from S3 (soft delete with retention)
     */
    public function deleteFile(PulseDavFile $s3File)
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
            Storage::disk('s3')->delete($s3File->s3_path);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete S3 file', [
                's3_file_id' => $s3File->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get processing status for a file
     */
    public function getProcessingStatus(PulseDavFile $s3File)
    {
        return [
            'id' => $s3File->id,
            'filename' => $s3File->filename,
            'status' => $s3File->status,
            'processed_at' => $s3File->processed_at,
            'error_message' => $s3File->error_message,
            'receipt_id' => $s3File->receipt_id,
        ];
    }

    /**
     * Process multiple files
     */
    public function processFiles(array $fileIds, User $user)
    {
        $files = PulseDavFile::whereIn('id', $fileIds)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        $queued = 0;
        foreach ($files as $file) {
            // Dispatch job to process this file
            \App\Jobs\ProcessPulseDavFile::dispatch($file);
            $file->markAsProcessing();
            $queued++;
        }

        return $queued;
    }

    /**
     * Generate a temporary download URL for a file
     */
    public function getTemporaryUrl(PulseDavFile $s3File, $expiration = 60)
    {
        try {
            $command = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $s3File->s3_path,
            ]);

            $request = $this->s3Client->createPresignedRequest($command, "+{$expiration} minutes");

            return (string) $request->getUri();
        } catch (\Exception $e) {
            Log::error('Failed to generate temporary URL', [
                's3_file_id' => $s3File->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
