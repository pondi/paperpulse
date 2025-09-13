<?php

namespace App\Services\Jobs;

use App\Models\JobHistory;

class JobParentStatusCalculator
{
    public static function calculate(JobHistory $parentJob): string
    {
        $children = $parentJob->tasks;
        if ($children->isEmpty()) {
            return $parentJob->status;
        }

        $taskGroups = $children->groupBy('name');
        $effectiveStatuses = [];
        foreach ($taskGroups as $attempts) {
            $latestAttempt = $attempts->sortByDesc('created_at')->first();
            $effectiveStatuses[] = $latestAttempt->status;
        }

        if (in_array('processing', $effectiveStatuses)) {
            return 'processing';
        }
        if (in_array('pending', $effectiveStatuses)) {
            return 'pending';
        }
        if (in_array('failed', $effectiveStatuses)) {
            return 'failed';
        }
        if (!empty($effectiveStatuses) && collect($effectiveStatuses)->every(fn($s) => $s === 'completed')) {
            return 'completed';
        }

        return $parentJob->status;
    }
}

