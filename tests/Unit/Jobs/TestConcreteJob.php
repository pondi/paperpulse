<?php

namespace Tests\Unit\Jobs;

use App\Jobs\BaseJob;
use RuntimeException;

class TestConcreteJob extends BaseJob
{
    public bool $shouldFail = false;

    public bool $executed = false;

    public function __construct(string $jobID, bool $shouldFail = false)
    {
        parent::__construct($jobID);
        $this->shouldFail = $shouldFail;
        $this->jobName = 'TestConcreteJob';
    }

    protected function handleJob(): void
    {
        $this->executed = true;

        if ($this->shouldFail) {
            throw new RuntimeException('Test failure message');
        }
    }
}
