<?php

namespace App\Console\Commands;

use App\Models\FileConversion;
use App\Services\Documents\ConversionService;
use Illuminate\Console\Command;

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
        $service = app(ConversionService::class);
        $requeued = $dryRun ? 0 : $service->reconcileAbandoned(max(1, min(100, $limit)));

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

        foreach ($failedConversions as $conversion) {
            if ($conversion->file === null) {
                continue;
            }
            $this->line("Conversion ID {$conversion->id} - File: {$conversion->file->fileName} ({$conversion->input_extension})");
            $this->line("  Error: {$conversion->error_message}");

            if (! $dryRun) {
                if (! $service->retry($conversion)) {
                    $this->warn("  Retry budget exhausted for conversion {$conversion->id}");

                    continue;
                }

                $this->info("  ✓ Requeued conversion {$conversion->id}");
                $requeued++;
            }

            $this->newLine();
        }

        if ($dryRun) {
            $this->warn('Dry run mode - no conversions were actually retried.');
            $this->info('Run without --dry-run to retry conversions.');
        } else {
            $this->info("Successfully requeued {$requeued} conversions.");
        }

        return 0;
    }
}
