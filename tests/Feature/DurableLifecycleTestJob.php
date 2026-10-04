<?php

namespace Tests\Feature;

use App\Jobs\BaseJob;
use RuntimeException;

class DurableLifecycleTestJob extends BaseJob
{
    public bool $shouldThrow = false;

    public int $executions = 0;

    protected function handleJob(): void
    {
        $this->executions++;
        if ($this->shouldThrow) {
            throw new RuntimeException('Transient provider failure');
        }
    }
}
