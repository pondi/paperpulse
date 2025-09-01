<?php

namespace App\Jobs\System;

use App\Jobs\BaseJob;
use App\Services\JobChainService;
use Illuminate\Support\Facades\Log;

class RestartJobChain extends BaseJob
{
    protected $originalJobId;

    public $timeout = 300;

    public $tries = 3;

    public $backoff = 30;

    public function __construct(string $jobID, string $originalJobId)
    {
        parent::__construct($jobID);
        $this->jobName = 'Restart Job Chain';
        $this->originalJobId = $originalJobId;
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        try {
            Log::info('[RestartJobChain] Starting job chain restart', [
                'job_id' => $this->jobID,
                'original_job_id' => $this->originalJobId,
            ]);

            $this->updateProgress(25);

            $jobChainService = app(JobChainService::class);

            $this->updateProgress(50);

            $result = $jobChainService->restartJobChain($this->originalJobId);

            if (! $result['success']) {
                throw new \Exception($result['message']);
            }

            $this->updateProgress(100);

            Log::info('[RestartJobChain] Job chain restart completed', [
                'job_id' => $this->jobID,
                'original_job_id' => $this->originalJobId,
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('[RestartJobChain] Job chain restart failed', [
                'job_id' => $this->jobID,
                'original_job_id' => $this->originalJobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
