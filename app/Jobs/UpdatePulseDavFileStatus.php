<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\File;
use App\Models\PulseDavFile;
use App\Models\Receipt;
use Illuminate\Support\Facades\Log;

class UpdatePulseDavFileStatus extends BaseJob
{
    protected $file;

    protected $pulseDavFileId;

    protected $type;

    /**
     * Create a new job instance.
     */
    public function __construct(string $jobID, File $file, $pulseDavFileId, $type = 'receipt')
    {
        parent::__construct($jobID);
        $this->file = $file;
        $this->pulseDavFileId = $pulseDavFileId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    protected function handleJob(): void
    {
        $pulseDavFile = PulseDavFile::find($this->pulseDavFileId);

        if (! $pulseDavFile) {
            Log::warning('PulseDavFile not found for status update', [
                'pulsedav_file_id' => $this->pulseDavFileId,
                'file_id' => $this->file->id,
            ]);

            return;
        }

        if ($this->type === 'document') {
            // Check if document was created
            $document = Document::where('file_id', $this->file->id)->first();

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
                    'file_id' => $this->file->id,
                ]);
            }
        } else {
            // Check if receipt was created
            $receipt = Receipt::where('file_id', $this->file->id)->first();

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
                    'file_id' => $this->file->id,
                ]);
            }
        }
    }
}
