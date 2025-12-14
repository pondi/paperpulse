<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Services\Files\FileReprocessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReprocessFiles extends Command
{
    protected $signature = 'files:reprocess
                            {--file-id=* : Reprocess specific file ID(s)}
                            {--type= : Filter by file type (receipt or document)}
                            {--status=failed : Filter by file status (failed, pending, processing, completed)}
                            {--force : Force reprocessing even if already completed}
                            {--limit= : Limit number of files to reprocess}
                            {--stats : Show reprocessing statistics and exit}
                            {--dry-run : Show what would be reprocessed without actually reprocessing}';

    protected $description = 'Reprocess failed or pending files from S3 (works for receipts and documents)';

    protected FileReprocessingService $reprocessingService;

    public function __construct(FileReprocessingService $reprocessingService)
    {
        parent::__construct();
        $this->reprocessingService = $reprocessingService;
    }

    public function handle()
    {
        // Show stats if requested
        if ($this->option('stats')) {
            $this->displayStats();

            return 0;
        }

        $this->info('🔄 Starting file reprocessing...');
        $this->newLine();

        // Get files to reprocess
        $files = $this->getFilesToReprocess();

        if ($files->isEmpty()) {
            $this->warn('No files found for reprocessing.');
            $this->newLine();
            $this->info('Tips:');
            $this->line('  • Use --status=<status> to filter by status (failed, pending, processing, completed)');
            $this->line('  • Use --type=<type> to filter by type (receipt, document)');
            $this->line('  • Use --stats to see reprocessing statistics');
            $this->line('  • Use --file-id=<id> to reprocess specific files');
            $this->newLine();

            return 0;
        }

        $this->info("Found {$files->count()} file(s) to reprocess:");
        $this->newLine();

        // Display files
        $this->displayFilesTable($files);
        $this->newLine();

        // Dry run mode
        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE: No files will be reprocessed.');
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
        Log::info('[ReprocessFiles] Reprocessing completed', [
            'successful' => $results['successful'],
            'failed' => $results['failed'],
            'skipped' => $results['skipped'],
            'total' => $files->count(),
            'type_filter' => $this->option('type'),
            'status_filter' => $this->option('status'),
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
            $query = File::whereIn('id', $fileIds)
                ->whereNotNull('s3_original_path')
                ->with('user');

            if ($type = $this->option('type')) {
                $query->where('file_type', $type);
            }

            return $query->get();
        }

        // Find by status and type
        $files = $this->reprocessingService->findReprocessableFiles(
            $this->option('type'),
            $this->option('status')
        );

        // Apply limit if specified
        if ($limit = $this->option('limit')) {
            $files = $files->take((int) $limit);
        }

        return $files;
    }

    /**
     * Display files table.
     */
    protected function displayFilesTable($files): void
    {
        $tableData = $files->map(function ($file) {
            return [
                'ID' => $file->id,
                'Type' => ucfirst($file->file_type),
                'GUID' => substr($file->guid, 0, 8).'...',
                'Filename' => strlen($file->fileName) > 25 ? substr($file->fileName, 0, 22).'...' : $file->fileName,
                'Status' => $file->status,
                'User' => $file->user->name ?? 'Unknown',
                'Uploaded' => $file->uploaded_at?->format('Y-m-d H:i') ?? 'N/A',
            ];
        })->toArray();

        $this->table(
            ['ID', 'Type', 'GUID', 'Filename', 'Status', 'User', 'Uploaded'],
            $tableData
        );
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
            $this->error('❌ Failed files:');
            foreach ($failures as $failure) {
                $this->error("  File {$failure['file_id']} ({$failure['file_name']}): {$failure['message']}");
            }
            $this->newLine();
        }

        // Show skipped files
        $skipped = collect($results['results'])->filter(fn ($r) => ! $r['success'] && str_contains($r['message'], 'already'));

        if ($skipped->isNotEmpty() && $skipped->count() <= 10) {
            $this->warn('⏭️  Skipped files:');
            foreach ($skipped as $skip) {
                $this->line("  File {$skip['file_id']}: {$skip['message']}");
            }
            $this->newLine();
        }

        // Show successful job IDs
        $successful = collect($results['results'])->filter(fn ($r) => $r['success']);

        if ($successful->isNotEmpty()) {
            $this->info('🚀 Started reprocessing jobs:');
            $displayCount = min($successful->count(), 10);
            foreach ($successful->take($displayCount) as $success) {
                $this->line("  File {$success['file_id']}: Job {$success['job_id']}");
            }

            if ($successful->count() > $displayCount) {
                $remaining = $successful->count() - $displayCount;
                $this->line("  ... and {$remaining} more");
            }
            $this->newLine();
        }

        // Next steps
        if ($results['successful'] > 0) {
            $this->info('💡 Monitor job progress:');
            $this->line('   php artisan horizon                              (Horizon dashboard)');
            $this->line('   tail -f storage/logs/laravel.log | grep Process  (Live logs)');
            $this->line('   php artisan files:reprocess --stats              (View statistics)');
        }
    }

    /**
     * Display reprocessing statistics.
     */
    protected function displayStats(): void
    {
        $this->info('📊 File Reprocessing Statistics');
        $this->newLine();

        $stats = $this->reprocessingService->getReprocessingStats();

        // Receipts section
        $this->info('Receipts:');
        $this->line("  Failed:     {$stats['failed_receipts']}");
        $this->line("  Pending:    {$stats['pending_receipts']}");
        $this->line("  Processing: {$stats['processing_receipts']}");
        $this->newLine();

        // Documents section
        $this->info('Documents:');
        $this->line("  Failed:     {$stats['failed_documents']}");
        $this->line("  Pending:    {$stats['pending_documents']}");
        $this->line("  Processing: {$stats['processing_documents']}");
        $this->newLine();

        // Totals
        $totalFailed = $stats['failed_receipts'] + $stats['failed_documents'];
        $totalPending = $stats['pending_receipts'] + $stats['pending_documents'];
        $totalProcessing = $stats['processing_receipts'] + $stats['processing_documents'];

        $this->info('Total:');
        $this->line("  Failed:     {$totalFailed}");
        $this->line("  Pending:    {$totalPending}");
        $this->line("  Processing: {$totalProcessing}");
        $this->newLine();

        // Suggestions
        if ($totalFailed > 0) {
            $this->warn("⚠️  You have {$totalFailed} failed file(s) that can be reprocessed.");
            $this->newLine();
            $this->info('Quick commands:');
            $this->line('  php artisan files:reprocess --status=failed                (Reprocess all failed)');
            $this->line('  php artisan files:reprocess --status=failed --type=receipt (Receipts only)');
            $this->line('  php artisan files:reprocess --status=failed --limit=10     (Limit to 10)');
            $this->line('  php artisan files:reprocess --status=failed --dry-run      (Preview first)');
        } elseif ($totalPending > 0) {
            $this->info("ℹ️  You have {$totalPending} pending file(s) that may need processing.");
        } else {
            $this->info('✅ No failed or pending files found.');
        }
    }
}
