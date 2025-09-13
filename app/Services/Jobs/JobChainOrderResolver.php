<?php

namespace App\Services\Jobs;

class JobChainOrderResolver
{
    public static function resolve(string $jobName): int
    {
        return \App\Jobs\JobOrder::getOrder($jobName);
    }
}
