<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\PulseDavFile;
use App\Services\PulseDavService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessPulseDavFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public $tries = 5;

    public $backoff = 10;

    protected $pulseDavFile;
    protected $tagIds;

    /**
     * Create a new job instance.
     */
    public function __construct(PulseDavFile $pulseDavFile, array $tagIds = [])
    {
        $this->pulseDavFile = $pulseDavFile;
        $this->tagIds = $tagIds;
    }

    /**
     * Execute the job.
     */
    public function handle(PulseDavService $pulseDavService)
    {
        try {
            // Mark as processing
            $this->pulseDavFile->markAsProcessing();

            // Download file from S3
            $fileContent = $pulseDavService->downloadFile($this->pulseDavFile->s3_path);

            // Store temporarily
            $tempPath = 'temp/'.Str::uuid().'_'.$this->pulseDavFile->filename;
            Storage::disk('local')->put($tempPath, $fileContent);

            // Create File record
            $file = File::create([
                'user_id' => $this->pulseDavFile->user_id,
                'file_path' => $tempPath,
                'original_filename' => $this->pulseDavFile->filename,
                'file_size' => $this->pulseDavFile->size,
                'mime_type' => Storage::disk('local')->mimeType($tempPath),
                'status' => 'pending',
                'file_type' => $this->pulseDavFile->file_type ?? 'receipt',
            ]);

            // Store PulseDavFile ID and tags in the File model for tracking
            $file->update(['meta' => json_encode([
                'pulsedav_file_id' => $this->pulseDavFile->id,
                'tag_ids' => $this->tagIds,
            ])]);

            // Generate a job ID for the chain
            $jobId = (string) Str::uuid();

            // Cache metadata for job chain
            $metadata = [
                'fileId' => $file->id,
                'fileGuid' => $file->guid ?? Str::uuid(),
                'filePath' => Storage::disk('local')->path($tempPath),
                'fileExtension' => pathinfo($this->pulseDavFile->filename, PATHINFO_EXTENSION),
                'fileType' => $this->pulseDavFile->file_type ?? 'receipt',
                'userId' => $this->pulseDavFile->user_id,
                's3OriginalPath' => $this->pulseDavFile->s3_path,
                'jobName' => 'PulseDav-' . $this->pulseDavFile->filename,
                'metadata' => ['tag_ids' => $this->tagIds],
                'source' => 'pulsedav',
            ];
            
            Cache::put("job.{$jobId}.fileMetaData", $metadata, now()->addHours(2));

            // Dispatch the appropriate processing chain based on file type
            if ($this->pulseDavFile->file_type === 'document') {
                Bus::chain([
                    new ProcessFile($jobId),
                    new ProcessDocument($jobId),
                    new AnalyzeDocument($jobId),
                    new ApplyTags($jobId, $file, $this->tagIds),
                    new DeleteWorkingFiles($jobId),
                    new UpdatePulseDavFileStatus($jobId, $file, $this->pulseDavFile->id, 'document'),
                ])->dispatch();
            } else {
                // Default to receipt processing
                Bus::chain([
                    new ProcessFile($jobId),
                    new ProcessReceipt($jobId),
                    new MatchMerchant($jobId),
                    new ApplyTags($jobId, $file, $this->tagIds),
                    new DeleteWorkingFiles($jobId),
                    new UpdatePulseDavFileStatus($jobId, $file, $this->pulseDavFile->id, 'receipt'),
                ])->dispatch();
            }

            // Update S3 file record
            $this->pulseDavFile->update([
                'status' => 'processing',
                'error_message' => null,
            ]);

            Log::info('S3 file processing started', [
                'pulsedav_file_id' => $this->pulseDavFile->id,
                'file_id' => $file->id,
            ]);

        } catch (\Exception $e) {
            $this->pulseDavFile->markAsFailed($e->getMessage());

            Log::error('Failed to process S3 file', [
                'pulsedav_file_id' => $this->pulseDavFile->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        $this->pulseDavFile->markAsFailed($exception->getMessage());
    }
}
