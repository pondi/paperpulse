<?php

namespace App\Jobs\Maintenance;

use App\Models\PulseDavFile;
use App\Services\PulseDavService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeletePulseDavFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $retentionDays;

    /**
     * Create a new job instance.
     */
    public function __construct($retentionDays = 30)
    {
        $this->retentionDays = $retentionDays;
    }

    /**
     * Execute the job.
     */
    public function handle(PulseDavService $pulseDavService)
    {
        Log::info('Starting PulseDav file cleanup', [
            'retention_days' => $this->retentionDays,
        ]);

        // Find completed files older than retention period
        $oldFiles = PulseDavFile::where('status', 'completed')
            ->where('processed_at', '<', Carbon::now()->subDays($this->retentionDays))
            ->get();

        $deletedCount = 0;
        $failedCount = 0;

        foreach ($oldFiles as $file) {
            try {
                $pulseDavService->deleteFile($file);
                $deletedCount++;

                Log::info('Deleted old PulseDav file', [
                    'pulsedav_file_id' => $file->id,
                    'filename' => $file->filename,
                    'processed_at' => $file->processed_at,
                ]);
            } catch (Exception $e) {
                $failedCount++;
                Log::error('Failed to delete PulseDav file', [
                    'pulsedav_file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed PulseDav file cleanup', [
            'total_files' => $oldFiles->count(),
            'deleted' => $deletedCount,
            'failed' => $failedCount,
        ]);
    }
}
