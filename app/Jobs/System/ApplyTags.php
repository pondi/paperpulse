<?php

namespace App\Jobs\System;

use App\Jobs\BaseJob;
use App\Models\File;
use App\Services\Tags\TagAttachmentService;
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
        Log::debug('[ApplyTags] Starting tag application', [
            'job_id' => $this->jobID,
            'file_id' => $this->file->id,
            'file_type' => $this->file->file_type,
            'tag_ids' => $this->tagIds,
            'tag_count' => count($this->tagIds),
        ]);

        if (empty($this->tagIds)) {
            Log::debug('[ApplyTags] No tags to apply', ['file_id' => $this->file->id]);

            return;
        }

        TagAttachmentService::attachTags($this->file, $this->tagIds);
    }
}
