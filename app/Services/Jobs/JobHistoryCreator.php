<?php

namespace App\Services\Jobs;

use App\Models\JobHistory;

/**
 * Creates and manages JobHistory records for job chains.
 *
 * Responsible for creating the parent job record that tracks
 * the entire job chain lifecycle and stores metadata.
 */
class JobHistoryCreator
{
    /**
     * Create the parent job history record with metadata.
     *
     * @param  string  $jobId  The unique job chain identifier
     * @param  string  $jobName  Human-readable job name
     * @param  string  $fileType  Either 'receipt' or 'document'
     * @param  array  $metadata  Complete job metadata including file details
     * @param  int  $fileId  The file being processed
     * @param  string|null  $fileName  Optional filename for tracking
     * @return JobHistory The created job history record
     */
    public static function createParentJob(
        string $jobId,
        string $jobName,
        string $fileType,
        array $metadata,
        int $fileId,
        ?string $fileName = null
    ): JobHistory {
        return JobHistory::create([
            'uuid' => $jobId,
            'parent_uuid' => null,
            'name' => $jobName,
            'queue' => $fileType === 'receipt' ? 'receipts' : 'documents',
            'status' => 'pending',
            'metadata' => $metadata,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'file_id' => $fileId,
            'order_in_chain' => 0,
        ]);
    }
}
