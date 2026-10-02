<?php

namespace App\Jobs\Maintenance;

use App\Jobs\BaseJob;
use App\Services\File\FileStorageService;
use Illuminate\Support\Facades\Cache;

class DeleteWorkingFiles extends BaseJob
{
    public function __construct(string $jobID)
    {
        parent::__construct($jobID);
        $this->jobName = 'Delete Working Files';
    }

    protected function handleJob(): void
    {
        app(FileStorageService::class)->cleanupJob($this->jobID);
        Cache::forget("job.{$this->jobID}.fileMetaData");
        Cache::forget("job.{$this->jobID}.receiptMetaData");
        $this->updateProgress(100);
    }
}
