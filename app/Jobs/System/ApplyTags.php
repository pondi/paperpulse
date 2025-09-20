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
        // Debug: Log initial state
        Log::info('[ApplyTags] Starting tag application', [
            'job_id' => $this->jobID,
            'file_id' => $this->file->id,
            'file_type' => $this->file->file_type,
            'tag_ids' => $this->tagIds,
            'tag_count' => count($this->tagIds),
        ]);

        if (empty($this->tagIds)) {
            Log::info('[ApplyTags] No tags to apply', ['file_id' => $this->file->id]);

            return;
        }

        try {
            // Debug: Log file details
            Log::info('[ApplyTags] File details', [
                'file_id' => $this->file->id,
                'file_guid' => $this->file->guid,
                'file_type' => $this->file->file_type,
                'file_type_is_null' => is_null($this->file->file_type),
                'file_type_empty' => empty($this->file->file_type),
            ]);

            // Find the receipt or document associated with this file
            if ($this->file->file_type === 'document') {
                Log::info('[ApplyTags] Looking for document with file_id', ['file_id' => $this->file->id]);
                $document = Document::where('file_id', $this->file->id)->first();

                if ($document) {
                    Log::info('[ApplyTags] Found document', [
                        'document_id' => $document->id,
                        'document_file_id' => $document->file_id,
                    ]);

                    // Sync tags to document with proper file_type pivot data
                    $pivotData = [];
                    foreach ($this->tagIds as $tagId) {
                        $pivotData[$tagId] = ['file_type' => 'document'];
                    }

                    Log::info('[ApplyTags] Prepared pivot data for document', [
                        'pivot_data' => $pivotData,
                    ]);

                    $document->tags()->syncWithoutDetaching($pivotData);

                    Log::info('[ApplyTags] Tags successfully applied to document', [
                        'document_id' => $document->id,
                        'tag_ids' => $this->tagIds,
                        'pivot_data' => $pivotData,
                    ]);
                } else {
                    Log::warning('[ApplyTags] No document found for file', ['file_id' => $this->file->id]);
                }
            } else {
                // Receipt type or null file_type
                Log::info('[ApplyTags] Looking for receipt with file_id', [
                    'file_id' => $this->file->id,
                    'file_type' => $this->file->file_type,
                ]);

                $receipt = Receipt::where('file_id', $this->file->id)->first();

                if ($receipt) {
                    Log::info('[ApplyTags] Found receipt', [
                        'receipt_id' => $receipt->id,
                        'receipt_file_id' => $receipt->file_id,
                    ]);

                    // Check if the receipt model uses TaggableModel trait
                    $usesTrait = in_array('App\Traits\TaggableModel', class_uses($receipt));
                    Log::info('[ApplyTags] Receipt uses TaggableModel trait', ['uses_trait' => $usesTrait]);

                    // Sync tags to receipt with proper file_type pivot data
                    $pivotData = [];
                    foreach ($this->tagIds as $tagId) {
                        $pivotData[$tagId] = ['file_type' => 'receipt'];
                    }

                    Log::info('[ApplyTags] Prepared pivot data for receipt', [
                        'pivot_data' => $pivotData,
                    ]);

                    // Debug: Check what the tags() relationship returns
                    $tagsRelation = $receipt->tags();
                    Log::info('[ApplyTags] Tags relationship details', [
                        'relation_class' => get_class($tagsRelation),
                        'pivot_table' => $tagsRelation->getTable(),
                        'foreign_pivot_key' => $tagsRelation->getForeignPivotKeyName(),
                        'related_pivot_key' => $tagsRelation->getRelatedPivotKeyName(),
                    ]);

                    $receipt->tags()->syncWithoutDetaching($pivotData);

                    Log::info('[ApplyTags] Tags successfully applied to receipt', [
                        'receipt_id' => $receipt->id,
                        'tag_ids' => $this->tagIds,
                        'pivot_data' => $pivotData,
                    ]);
                } else {
                    Log::warning('[ApplyTags] No receipt found for file', ['file_id' => $this->file->id]);
                }
            }
        } catch (\Exception $e) {
            Log::error('[ApplyTags] Failed to apply tags - Exception details', [
                'file_id' => $this->file->id,
                'file_type' => $this->file->file_type,
                'tag_ids' => $this->tagIds,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
