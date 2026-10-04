<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\FileProcessingRequest;
use App\Services\Files\FileProcessingCapabilities;
use App\Services\Files\FileReprocessingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ReprocessFiles extends Command
{
    protected $signature = 'files:reprocess
                            {--all : Refresh all stored files, including completed files, using the current AI pipeline and fresh AI results}
                            {--file-id=* : Reprocess specific file ID(s)}
                            {--user= : Only reprocess files owned by this user ID}
                            {--type= : Filter by file type (receipt or document)}
                            {--status=failed : Filter by status (failed, pending, processing, completed, needs_review, all)}
                            {--provider= : Override the AI pipeline (gemini, textract+openai, ocr-only)}
                            {--fresh : Clear cached processing stages before reprocessing}
                            {--force : Reprocess completed files, restart active jobs, and skip confirmation}
                            {--limit= : Limit number of files to reprocess}
                            {--after-id=0 : Resume after this file ID}
                            {--chunk=100 : Number of files to load at a time}
                            {--stats : Show reprocessing statistics and exit}
                            {--dry-run : Preview the selection without changing data or dispatching jobs}';

    protected $description = 'Retry files or refresh the entire archive with the latest AI processing';

    public function __construct(protected FileReprocessingService $reprocessingService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('stats')) {
            $this->displayStats();

            return self::SUCCESS;
        }

        try {
            $this->validateOptions();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $refreshAll = $this->refreshAll();
        $provider = $this->option('provider') ?: ($refreshAll ? config('ai.file_processing_provider', 'textract+openai') : null);
        $fresh = $refreshAll || (bool) $this->option('fresh');
        $query = $this->filesQuery();
        $maxId = (clone $query)->max('id');
        $query->where('id', '<=', $maxId ?? 0);
        $total = min((clone $query)->count(), (int) ($this->option('limit') ?? PHP_INT_MAX));

        if ($total === 0) {
            $this->warn('No files found for reprocessing. Use --all to include completed files or --stats to inspect the archive.');

            return self::SUCCESS;
        }

        $this->info("Selected {$total} file(s) for reprocessing.");
        $this->line('AI pipeline: '.($provider ?? 'previous pipeline, falling back to current configuration'));
        $this->line('Cached processing results: '.($fresh ? 'refresh' : 'reuse when configuration matches'));
        if ($refreshAll && ! $this->option('force')) {
            $this->line('Files with active processing are excluded. Use --force to restart them.');
        }
        $this->displayFilesTable((clone $query)->with('user')->orderBy('id')->limit(min($total, 20))->get());
        if ($total > 20) {
            $this->line('Showing the first 20 files.');
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: no data, caches, or jobs were changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && $this->input->isInteractive()
            && ! $this->confirm('Reprocess these files? This may incur AI and OCR charges.', true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $results = ['queued' => 0, 'failed' => 0, 'skipped' => 0];
        $processed = 0;
        $lastId = (int) $this->option('after-id');
        $progress = $this->output->createProgressBar($total);
        $progress->start();

        foreach ($query->lazyById((int) $this->option('chunk')) as $file) {
            $result = $this->reprocessingService->reprocessFile(
                $file,
                force: $refreshAll || (bool) $this->option('force'),
                provider: $provider,
                fresh: $fresh,
                skipActive: $refreshAll && ! $this->option('force'),
            );
            if ($result['success']) {
                $results['queued']++;
            } elseif (str_contains($result['message'], 'already') || str_contains($result['message'], 'currently')) {
                $results['skipped']++;
            } else {
                $results['failed']++;
                $this->newLine();
                $this->error("File {$file->id}: {$result['message']}");
            }
            $lastId = $file->id;
            $progress->advance();
            if (++$processed >= $total) {
                break;
            }
        }

        $progress->finish();
        $this->newLine(2);
        $this->info("Queued: {$results['queued']}; failed to queue: {$results['failed']}; skipped: {$results['skipped']}.");
        $this->line("Last visited file ID: {$lastId}. Use --after-id={$lastId} to continue; retry failed file IDs separately.");
        if ($results['queued'] > 0) {
            $this->line('Queue handoff finished. Extraction, search updates, and organization continue in the workers.');
            $this->line('Monitor with: php artisan queue:health');
        }
        Log::info('[ReprocessFiles] Queue handoff finished', $results + [
            'total' => $processed, 'last_file_id' => $lastId, 'max_file_id' => $maxId,
            'refresh_all' => $refreshAll, 'provider' => $provider, 'fresh' => $fresh,
            'user_filter' => $this->option('user'), 'type_filter' => $this->option('type'),
            'status_filter' => $this->option('status'),
        ]);

        return $results['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function refreshAll(): bool
    {
        return (bool) $this->option('all') || $this->option('status') === 'all';
    }

    protected function validateOptions(): void
    {
        if ($this->option('type') !== null && ! in_array($this->option('type'), ['receipt', 'document'], true)) {
            throw new InvalidArgumentException('Use --type=receipt or --type=document.');
        }
        if (! in_array($this->option('status'), ['failed', 'pending', 'processing', 'completed', 'needs_review', 'all'], true)) {
            throw new InvalidArgumentException('Invalid --status. Use failed, pending, processing, completed, needs_review, or all.');
        }
        if ($this->option('all') && $this->input->hasParameterOption('--status') && $this->option('status') !== 'all') {
            throw new InvalidArgumentException('Use either --all or a specific --status, not both.');
        }
        foreach (['user', 'limit', 'chunk', 'after-id'] as $option) {
            $value = $this->option($option);
            $minimum = $option === 'after-id' ? 0 : 1;
            if ($value !== null && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]) === false) {
                throw new InvalidArgumentException("--{$option} must be an integer of at least {$minimum}.");
            }
        }
        if ((int) $this->option('chunk') > 1000) {
            throw new InvalidArgumentException('--chunk must be between 1 and 1000.');
        }
        foreach ($this->option('file-id') as $fileId) {
            if (filter_var($fileId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new InvalidArgumentException('--file-id must be a positive integer.');
            }
        }
        $provider = $this->option('provider') ?? ($this->refreshAll() ? config('ai.file_processing_provider', 'textract+openai') : null);
        if ($provider !== null) {
            new FileProcessingCapabilities($provider);
        }
    }

    /** @return Builder<File> */
    protected function filesQuery(): Builder
    {
        $query = File::withoutGlobalScope('user')->whereNotNull('s3_original_path')->where('s3_original_path', '!=', '')
            ->where('id', '>', (int) $this->option('after-id'));
        if ($fileIds = $this->option('file-id')) {
            $query->whereIn('id', $fileIds);
        } elseif (! $this->refreshAll()) {
            $query->where('status', $this->option('status'));
        }
        if ($type = $this->option('type')) {
            $query->where('file_type', $type);
        }
        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }
        if ($this->refreshAll() && ! $this->option('force')) {
            $query->where('status', '!=', 'processing')
                ->whereDoesntHave('processingJobs', fn (Builder $jobs) => $jobs->whereIn('status', ['pending', 'processing', 'retrying']))
                ->whereNotIn('id', FileProcessingRequest::query()->select('file_id')->whereIn('state', ['upload_pending', 'pending', 'dispatching']));
        }

        return $query;
    }

    /** @param Collection<int, File> $files */
    protected function displayFilesTable(Collection $files): void
    {
        $this->table(['ID', 'Type', 'Filename', 'Status', 'Owner'], $files->map(fn (File $file): array => [
            $file->id, $file->file_type, $file->fileName, $file->status, $file->user->name ?? 'Unknown',
        ])->all());
    }

    protected function displayStats(): void
    {
        $stats = $this->reprocessingService->getReprocessingStats();
        $this->table(['Type', 'Failed', 'Pending', 'Processing', 'Completed', 'Needs review'], collect(['receipts', 'documents'])
            ->map(fn (string $type): array => [
                ucfirst($type), $stats["failed_{$type}"], $stats["pending_{$type}"], $stats["processing_{$type}"],
                $stats["completed_{$type}"], $stats["needs_review_{$type}"],
            ])->all());
        $this->line('Refresh the archive: php artisan files:reprocess --all');
        $this->line('Preview first: php artisan files:reprocess --all --dry-run');
    }
}
