<?php

namespace App\Services\Jobs;

use App\Jobs\JobOrder;

class JobChainOrderResolver
{
    public static function resolve(string $jobName): int
    {
        return JobOrder::getOrder($jobName);
    }
}
