<?php

namespace App\Jobs;

class JobOrder
{
    /**
     * Get the order in the job chain for a given job name or class basename.
     */
    public static function getOrder(string $jobName): int
    {
        $name = trim($jobName);

        return match ($name) {
            // Parent job for PulseDav imports
            'ProcessPulseDavFile', 'Process PulseDav File' => 0,

            // Standard processing chain
            'ProcessFile', 'Process File' => 1,
            'ProcessReceipt', 'Process Receipt' => 2,
            'ProcessDocument', 'Process Document' => 2,
            'MatchMerchant', 'Match Merchant' => 3,
            'AnalyzeDocument', 'Analyze Document' => 3,
            'ApplyTags', 'Apply Tags' => 4,
            'DeleteWorkingFiles', 'Delete Working Files' => 5,
            'UpdatePulseDavFileStatus', 'Update PulseDav File Status' => 6,

            // Maintenance/other
            'RestartJobChain', 'Restart Job Chain' => 0,

            default => 0,
        };
    }
}
