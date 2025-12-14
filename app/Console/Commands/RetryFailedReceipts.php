<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Services\Files\FileReprocessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedReceipts extends Command
{
    protected $signature = 'receipts:retry-failed
                            {--file-id=* : Retry specific file ID(s)}
                            {--status=failed : Filter by file status (failed, pending, processing, completed)}
                            {--force : Force reprocessing even if already completed}
                            {--limit= : Limit number of files to retry}
                            {--dry-run : Show what would be retried without actually retrying}';

    protected $description = 'Retry failed receipt processing jobs (reprocesses from S3)';

    protected FileReprocessingService $reprocessingService;

    public function __construct(FileReprocessingService $reprocessingService)
    {
        parent::__construct();
        $this->reprocessingService = $reprocessingService;
    }

    public function handle()
    {
        $this->info('🔄 Starting receipt reprocessing...');
        $this->newLine();

        // Get files to reprocess
        $files = $this->getFilesToReprocess();

        if ($files->isEmpty()) {
            $this->warn('No receipt files found for reprocessing.');
            $this->info('Use --status=<status> to filter by different status (failed, pending, processing, completed)');

            return 0;
        }

        $this->info("Found {$files->count()} receipt file(s) to reprocess:");
        $this->newLine();

        // Display files
        $tableData = $files->map(function ($file) {
            return [
                'ID' => $file->id,
                'GUID' => substr($file->guid, 0, 8).'...',
                'Filename' => strlen($file->fileName) > 30 ? substr($file->fileName, 0, 27).'...' : $file->fileName,
                'Status' => $file->status,
                'User' => $file->user->name ?? 'Unknown',
                'Uploaded' => $file->uploaded_at?->format('Y-m-d H:i') ?? 'N/A',
            ];
        })->toArray();

        $this->table(
            ['ID', 'GUID', 'Filename', 'Status', 'User', 'Uploaded'],
            $tableData
        );

        $this->newLine();

        // Dry run mode
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE: No files will be reprocessed.');
            $this->info('Remove --dry-run flag to actually reprocess these files.');

            return 0;
        }

        // Confirm before proceeding
        if (! $this->option('force') && ! $this->confirm('Do you want to reprocess these files?', true)) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $this->newLine();
        $this->info('Starting reprocessing...');
        $this->newLine();

        // Create progress bar
        $progressBar = $this->output->createProgressBar($files->count());
        $progressBar->start();

        // Reprocess files
        $results = $this->reprocessingService->reprocessFiles($files, $this->option('force'));

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->displayResults($results);

        // Log results
        Log::info('[RetryFailedReceipts] Reprocessing completed', [
            'successful' => $results['successful'],
            'failed' => $results['failed'],
            'skipped' => $results['skipped'],
            'total' => $files->count(),
        ]);

        return 0;
    }

    /**
     * Get files to reprocess based on options.
     */
    protected function getFilesToReprocess()
    {
        // Specific file IDs provided
        if ($fileIds = $this->option('file-id')) {
            return File::whereIn('id', $fileIds)
                ->where('file_type', 'receipt')
                ->whereNotNull('s3_original_path')
                ->with('user')
                ->get();
        }

        // Find by status
        $status = $this->option('status');
        $files = $this->reprocessingService->findReprocessableFiles('receipt', $status);

        // Apply limit if specified
        if ($limit = $this->option('limit')) {
            $files = $files->take((int) $limit);
        }

        return $files;
    }

    /**
     * Display reprocessing results.
     */
    protected function displayResults(array $results): void
    {
        $this->info('✅ Reprocessing Results:');
        $this->newLine();

        // Summary
        $this->info("  Successful: {$results['successful']}");
        $this->info("  Failed:     {$results['failed']}");
        $this->info("  Skipped:    {$results['skipped']}");
        $this->newLine();

        // Show failures if any
        $failures = collect($results['results'])->filter(fn ($r) => ! $r['success'] && ! str_contains($r['message'], 'already'));

        if ($failures->isNotEmpty()) {
            $this->error('Failed files:');
            foreach ($failures as $failure) {
                $this->error("  File {$failure['file_id']} ({$failure['file_name']}): {$failure['message']}");
            }
            $this->newLine();
        }

        // Show successful job IDs
        $successful = collect($results['results'])->filter(fn ($r) => $r['success']);

        if ($successful->isNotEmpty()) {
            $this->info('Started reprocessing jobs:');
            foreach ($successful as $success) {
                $this->line("  File {$success['file_id']}: Job {$success['job_id']}");
            }
            $this->newLine();
        }

        // Next steps
        if ($results['successful'] > 0) {
            $this->info('💡 Monitor job progress:');
            $this->line('   php artisan horizon         (View Horizon dashboard)');
            $this->line('   tail -f storage/logs/laravel.log | grep ProcessReceipt');
        }
    }
}
