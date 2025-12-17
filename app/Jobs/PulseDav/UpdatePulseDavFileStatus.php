<?php

namespace App\Jobs\PulseDav;

use App\Jobs\BaseJob;
use App\Models\Document;
use App\Models\File;
use App\Models\PulseDavFile;
use App\Models\Receipt;
use Illuminate\Support\Facades\Log;

class UpdatePulseDavFileStatus extends BaseJob
{
    protected $fileId;

    protected $pulseDavFileId;

    protected $type;

    /**
     * Create a new job instance.
     */
    public function __construct(string $jobID, int $fileId, $pulseDavFileId, $type = 'receipt')
    {
        parent::__construct($jobID);
        $this->fileId = $fileId;
        $this->pulseDavFileId = $pulseDavFileId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    protected function handleJob(): void
    {
        // Query for File when job actually runs (not at dispatch time)
        $file = File::find($this->fileId);

        if (! $file) {
            Log::error('File not found for PulseDav status update', [
                'file_id' => $this->fileId,
                'pulsedav_file_id' => $this->pulseDavFileId,
            ]);

            return;
        }

        $pulseDavFile = PulseDavFile::find($this->pulseDavFileId);

        if (! $pulseDavFile) {
            Log::warning('PulseDavFile not found for status update', [
                'pulsedav_file_id' => $this->pulseDavFileId,
                'file_id' => $file->id,
            ]);

            return;
        }

        if ($this->type === 'document') {
            // Check if document was created
            $document = Document::where('file_id', $file->id)->first();

            if ($document) {
                $pulseDavFile->markAsCompleted($document->id, 'document');
                Log::info('PulseDavFile marked as completed', [
                    'pulsedav_file_id' => $pulseDavFile->id,
                    'document_id' => $document->id,
                ]);
            } else {
                $pulseDavFile->markAsFailed('No document created from file');
                Log::error('PulseDavFile processing failed - no document created', [
                    'pulsedav_file_id' => $pulseDavFile->id,
                    'file_id' => $file->id,
                ]);
            }
        } else {
            // Check if receipt was created
            $receipt = Receipt::where('file_id', $file->id)->first();

            if ($receipt) {
                $pulseDavFile->markAsCompleted($receipt->id, 'receipt');
                Log::info('PulseDavFile marked as completed', [
                    'pulsedav_file_id' => $pulseDavFile->id,
                    'receipt_id' => $receipt->id,
                ]);
            } else {
                $pulseDavFile->markAsFailed('No receipt created from file');
                Log::error('PulseDavFile processing failed - no receipt created', [
                    'pulsedav_file_id' => $pulseDavFile->id,
                    'file_id' => $file->id,
                ]);
            }
        }
    }
}
