<?php

namespace App\Console\Commands;

use App\Models\FileConversion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RetryFailedConversions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversions:retry-failed 
                            {--limit=10 : Number of conversions to retry}
                            {--dry-run : Show what would be retried without retrying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed office file conversions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Searching for failed conversions...');

        $failedConversions = FileConversion::where('status', 'failed')
            ->where('input_extension', '!=', 'pdf')
            ->with('file')
            ->limit($limit)
            ->get();

        if ($failedConversions->isEmpty()) {
            $this->info('No failed conversions found.');

            return 0;
        }

        $this->info("Found {$failedConversions->count()} failed conversions.");
        $this->newLine();

        $redis = Redis::connection('conversion');
        $redisQueue = config('processing.conversion.redis_queue', 'conversion:pending');

        foreach ($failedConversions as $conversion) {
            $this->line("Conversion ID {$conversion->id} - File: {$conversion->file->fileName} ({$conversion->input_extension})");
            $this->line("  Error: {$conversion->error_message}");

            if (! $dryRun) {
                // Reset retry count and status
                $conversion->update([
                    'status' => 'pending',
                    'retry_count' => 0,
                    'error_message' => null,
                    'started_at' => null,
                    'completed_at' => null,
                ]);

                // Re-push to Redis queue
                $payload = [
                    'conversionId' => $conversion->id,
                    'fileId' => $conversion->file_id,
                    'fileGuid' => $conversion->file->guid,
                    'userId' => $conversion->user_id,
                    'inputS3Path' => $conversion->input_s3_path,
                    'outputS3Path' => $conversion->output_s3_path,
                    'inputExtension' => $conversion->input_extension,
                    'retryCount' => 0,
                    'maxRetries' => $conversion->max_retries,
                    'createdAt' => now()->toIso8601String(),
                    'timeout' => config('processing.conversion.timeout', 120),
                ];

                $redis->lpush($redisQueue, json_encode($payload));

                // Update Redis status hash
                $redis->hset("conversion:status:{$conversion->id}", 'status', 'pending');
                $redis->hset("conversion:status:{$conversion->id}", 'updated_at', now()->toIso8601String());
                $redis->expire("conversion:status:{$conversion->id}", 7200);

                $this->info("  ✓ Requeued conversion {$conversion->id}");
            }

            $this->newLine();
        }

        if ($dryRun) {
            $this->warn('Dry run mode - no conversions were actually retried.');
            $this->info('Run without --dry-run to retry conversions.');
        } else {
            $this->info("Successfully requeued {$failedConversions->count()} conversions.");
        }

        return 0;
    }
}
