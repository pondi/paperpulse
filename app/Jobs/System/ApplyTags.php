<?php

namespace App\Jobs\System;

use App\Jobs\BaseJob;
use App\Models\Document;
use App\Models\File;
use App\Models\Receipt;
use Illuminate\Support\Facades\Log;

class ApplyTags extends BaseJob
{
    protected $file;

    protected $tagIds;

    /**
     * Create a new job instance.
     */
    public function __construct(string $jobID, File $file, array $tagIds = [])
    {
        parent::__construct($jobID);
        $this->file = $file;
        $this->tagIds = $tagIds;
    }

    /**
     * Execute the job.
     */
    protected function handleJob(): void
    {
        if (empty($this->tagIds)) {
            Log::info('No tags to apply', ['file_id' => $this->file->id]);

            return;
        }

        try {
            // Find the receipt or document associated with this file
            if ($this->file->file_type === 'document') {
                $document = Document::where('file_id', $this->file->id)->first();

                if ($document) {
                    // Sync tags to document
                    $document->tags()->syncWithoutDetaching($this->tagIds);

                    Log::info('Tags applied to document', [
                        'document_id' => $document->id,
                        'tag_ids' => $this->tagIds,
                    ]);
                }
            } else {
                // Receipt type
                $receipt = Receipt::where('file_id', $this->file->id)->first();

                if ($receipt) {
                    // Sync tags to receipt
                    $receipt->tags()->syncWithoutDetaching($this->tagIds);

                    Log::info('Tags applied to receipt', [
                        'receipt_id' => $receipt->id,
                        'tag_ids' => $this->tagIds,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to apply tags', [
                'file_id' => $this->file->id,
                'tag_ids' => $this->tagIds,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
