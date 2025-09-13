<?php

namespace App\Services\Jobs;

use App\Models\JobHistory;
use Illuminate\Support\Facades\DB;

class JobStatisticsProvider
{
    public static function overall(): array
    {
        $parentJobs = JobHistory::whereNull('parent_uuid')->with('tasks')->get();

        $stats = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        foreach ($parentJobs as $job) {
            $children = $job->tasks;
            if ($children->isEmpty()) {
                $stats[$job->status]++;
            } else {
                foreach ($children->groupBy('name') as $attempts) {
                    $latestAttempt = $attempts->sortByDesc('created_at')->first();
                    $taskStatus = $latestAttempt->status;
                    if (isset($stats[$taskStatus])) {
                        $stats[$taskStatus]++;
                    }
                }
            }
        }

        $stats['pending'] += DB::table('jobs')->count();

        return $stats;
    }
}
