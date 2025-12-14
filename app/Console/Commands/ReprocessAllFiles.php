<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Services\FileProcessingService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReprocessAllFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:reprocess-all
                            {--type= : File type to reprocess (receipt/document/all)}
                            {--user= : User ID to filter by}
                            {--limit=100 : Maximum number of files to reprocess}
                            {--failed : Only reprocess files that previously failed}
                            {--dry-run : Show what would be reprocessed without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess all uploaded files through the processing pipeline';

    protected FileProcessingService $fileProcessingService;

    /**
     * Create a new command instance.
     */
    public function __construct(FileProcessingService $fileProcessingService)
    {
        parent::__construct();
        $this->fileProcessingService = $fileProcessingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type') ?? 'all';
        $userId = $this->option('user');
        $limit = (int) $this->option('limit');
        $failedOnly = $this->option('failed');
        $dryRun = $this->option('dry-run');

        $this->info('Starting file reprocessing...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will actually be reprocessed');
        }

        // Get files to reprocess
        $files = $this->getFilesToReprocess($type, $userId, $limit, $failedOnly);

        if ($files->isEmpty()) {
            $this->info('No files found to reprocess.');

            return 0;
        }

        $this->info("Found {$files->count()} files to reprocess");

        $progressBar = $this->output->createProgressBar($files->count());
        $progressBar->start();

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($files as $file) {
            try {
                $progressBar->advance();

                if ($dryRun) {
                    $this->line("\nWould reprocess: {$file->original_filename} (ID: {$file->id}, Type: {$file->file_type})");
                    $successful++;

                    continue;
                }

                // Check if file has content in storage
                if (! $file->file_path) {
                    $this->warn("\nSkipping file without storage path: {$file->original_filename}");
                    $skipped++;

                    continue;
                }

                // Prepare file data for reprocessing
                $fileData = [
                    'fileName' => $file->original_filename,
                    'fileSize' => $file->file_size,
                    'mimeType' => $file->mime_type,
                    'extension' => pathinfo($file->original_filename, PATHINFO_EXTENSION),
                    'source' => 'reprocess',
                    'content' => null, // Will be loaded from storage
                ];

                // Load file content from storage
                $storagePath = storage_path('app/uploads/'.$file->file_guid.'.'.$fileData['extension']);
                if (! file_exists($storagePath)) {
                    $this->warn("\nFile not found in local storage: {$storagePath}");
                    $skipped++;

                    continue;
                }

                $fileData['content'] = file_get_contents($storagePath);

                // Generate new job ID for reprocessing
                $jobId = (string) Str::uuid();
                $metadata = [
                    'jobId' => $jobId,
                    'jobName' => 'Reprocess: '.$file->original_filename,
                    'originalFileId' => $file->id,
                    'reprocessedAt' => now()->toIso8601String(),
                ];

                // Reprocess the file
                $result = $this->fileProcessingService->processFile(
                    $fileData,
                    $file->file_type,
                    $file->user_id,
                    $metadata
                );

                if ($result['success']) {
                    $this->info("\n✓ Reprocessed: {$file->original_filename}");
                    $successful++;
                } else {
                    $this->error("\n✗ Failed to reprocess: {$file->original_filename}");
                    $failed++;
                }

            } catch (Exception $e) {
                $this->error("\n✗ Error reprocessing {$file->original_filename}: ".$e->getMessage());
                Log::error('File reprocessing failed', [
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Show summary
        $this->info('Reprocessing complete!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Successful', $successful],
                ['Failed', $failed],
                ['Skipped', $skipped],
                ['Total', $files->count()],
            ]
        );

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Get files to reprocess based on filters
     */
    protected function getFilesToReprocess($type, $userId, $limit, $failedOnly)
    {
        $query = File::query();

        // Filter by type
        if ($type !== 'all') {
            $query->where('file_type', $type);
        }

        // Filter by user
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Filter by failed status if requested
        if ($failedOnly) {
            // Get files that don't have successful receipts/documents
            $query->whereDoesntHave('receipts')
                ->whereDoesntHave('documents');
        }

        // Apply limit
        $query->limit($limit);

        // Order by oldest first
        $query->orderBy('created_at', 'asc');

        return $query->get();
    }
}
