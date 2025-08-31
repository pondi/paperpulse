<?php

namespace App\Console\Commands;

use App\Jobs\ProcessReceipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryFailedReceipts extends Command
{
    protected $signature = 'receipts:retry-failed {--all : Retry all failed receipt jobs} {--job-id= : Retry specific job ID} {--clear : Clear failed jobs after successful retry}';

    protected $description = 'Retry failed receipt processing jobs';

    public function handle()
    {
        $this->info('Starting failed receipt job retry process...');

        $query = DB::table('failed_jobs')
            ->where('queue', 'receipts')
            ->whereRaw("payload::json->>'displayName' = ?", ['App\\Jobs\\ProcessReceipt']);

        if ($this->option('job-id')) {
            $query->whereRaw("payload::json->'data'->>'command' LIKE ?", ['%'.$this->option('job-id').'%']);
        }

        $failedJobs = $query->get();

        if ($failedJobs->isEmpty()) {
            $this->info('No failed receipt jobs found.');

            return 0;
        }

        $this->info("Found {$failedJobs->count()} failed receipt job(s).");

        $successful = 0;
        $failed = 0;

        foreach ($failedJobs as $failedJob) {
            try {
                $payload = json_decode($failedJob->payload, true);
                $command = unserialize($payload['data']['command']);

                if ($command instanceof ProcessReceipt) {
                    $this->info("Retrying job: {$command->jobID}");

                    // Dispatch the job again
                    ProcessReceipt::dispatch($command->jobID)
                        ->onQueue('receipts')
                        ->delay(now()->addSeconds(2));

                    $successful++;

                    // Remove the failed job record if requested
                    if ($this->option('clear')) {
                        DB::table('failed_jobs')->where('id', $failedJob->id)->delete();
                        $this->info("Cleared failed job record for {$command->jobID}");
                    }

                    // Small delay between retries
                    sleep(1);
                } else {
                    $this->warn("Skipping non-ProcessReceipt job: {$failedJob->id}");
                }
            } catch (\Exception $e) {
                $this->error("Failed to retry job {$failedJob->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Retry completed: {$successful} successful, {$failed} failed");

        Log::info('Failed receipt jobs retry completed', [
            'successful' => $successful,
            'failed' => $failed,
            'cleared' => $this->option('clear'),
        ]);

        return 0;
    }
}
