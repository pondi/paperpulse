<?php

namespace App\Jobs\PulseDav;

use App\Jobs\BaseJob;
use App\Models\PulseDavFile;
use App\Services\PulseDav\ImportService;

class UpdatePulseDavFileStatus extends BaseJob
{
    public function __construct(string $jobID, protected int $fileId, protected int $pulseDavFileId, protected string $type = 'receipt')
    {
        parent::__construct($jobID);
    }

    protected function handleJob(): void
    {
        $source = PulseDavFile::query()->where('job_id', $this->jobID)->where('file_id', $this->fileId)->find($this->pulseDavFileId);
        if ($source !== null) {
            ImportService::reconcileFile($source);
        }
    }
}
