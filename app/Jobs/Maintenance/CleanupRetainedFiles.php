<?php

namespace App\Jobs\Maintenance;

use App\Enums\DeletedReason;
use App\Jobs\BaseJob;
use App\Models\Receipt;
use App\Models\User;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CleanupRetainedFiles extends BaseJob
{
    public function __construct()
    {
        parent::__construct(Str::uuid());
        $this->jobName = 'Cleanup Retained Files';
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        Log::info('Starting cleanup of retained files');

        $usersWithRetention = User::whereHas('preferences', function ($query) {
            $query->where('delete_after_processing', true)
                ->where('file_retention_days', '>', 0);
        })->with('preferences')->get();

        foreach ($usersWithRetention as $user) {
            $retentionDays = $user->preference('file_retention_days', 30);
            $cutoffDate = Carbon::now()->subDays($retentionDays);

            Log::info('Processing file cleanup for user', [
                'user_id' => $user->id,
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]);

            // Find receipts older than retention period that have processed files
            $receiptsToCleanup = Receipt::where('user_id', $user->id)
                ->where('created_at', '<', $cutoffDate)
                ->whereHas('file')
                ->with('file')
                ->get();

            foreach ($receiptsToCleanup as $receipt) {
                if ($receipt->file) {
                    try {
                        // Soft delete the file with user delete reason
                        // (User configured this retention cleanup)
                        $receipt->file->deleted_reason = DeletedReason::UserDelete;
                        $receipt->file->save();
                        $receipt->file->delete();

                        Log::info('Soft deleted retained file', [
                            'receipt_id' => $receipt->id,
                            'file_id' => $receipt->file->id,
                            'user_id' => $user->id,
                        ]);
                    } catch (Exception $e) {
                        Log::error('Failed to soft delete retained file', [
                            'receipt_id' => $receipt->id,
                            'file_id' => $receipt->file->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        Log::info('Completed cleanup of retained files');
    }
}
