<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\PulseDavFile;
use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdatePulseDavFileStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $file;

    protected $pulseDavFileId;

    /**
     * Create a new job instance.
     */
    public function __construct(File $file, $pulseDavFileId)
    {
        $this->file = $file;
        $this->pulseDavFileId = $pulseDavFileId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $pulseDavFile = PulseDavFile::find($this->pulseDavFileId);

        if (! $pulseDavFile) {
            Log::warning('PulseDavFile not found for status update', [
                'pulsedav_file_id' => $this->pulseDavFileId,
                'file_id' => $this->file->id,
            ]);

            return;
        }

        // Check if receipt was created
        $receipt = Receipt::where('file_id', $this->file->id)->first();

        if ($receipt) {
            $pulseDavFile->markAsCompleted($receipt->id);
            Log::info('PulseDavFile marked as completed', [
                'pulsedav_file_id' => $pulseDavFile->id,
                'receipt_id' => $receipt->id,
            ]);
        } else {
            $pulseDavFile->markAsFailed('No receipt created from file');
            Log::error('PulseDavFile processing failed - no receipt created', [
                'pulsedav_file_id' => $pulseDavFile->id,
                'file_id' => $this->file->id,
            ]);
        }
    }
}
