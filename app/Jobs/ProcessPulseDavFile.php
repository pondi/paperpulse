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

            // Dispatch the appropriate processing chain based on file type
            if ($this->pulseDavFile->file_type === 'document') {
                ProcessFile::withChain([
                    new ProcessDocument($file),
                    new AnalyzeDocument($file),
                    new ApplyTags($file, $this->tagIds),
                    new DeleteWorkingFiles($file),
                    new UpdatePulseDavFileStatus($file, $this->pulseDavFile->id, 'document'),
                ])->dispatch($file);
            } else {
                // Default to receipt processing
                ProcessFile::withChain([
                    new ProcessReceipt($file),
                    new MatchMerchant($file),
                    new ApplyTags($file, $this->tagIds),
                    new DeleteWorkingFiles($file),
                    new UpdatePulseDavFileStatus($file, $this->pulseDavFile->id, 'receipt'),
                ])->dispatch($file);
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
