<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Services\Files\FilePreviewManager;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RegenerateImagePreviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:regenerate-previews
                            {--type= : File type to process (receipt or document)}
                            {--limit=0 : Number of files to process (0 for all)}
                            {--force : Regenerate even if preview exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate image previews for PDF files (receipts and documents)';

    /**
     * Execute the console command.
     */
    public function handle(FilePreviewManager $previewManager)
    {
        $type = $this->option('type');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $this->info('Starting image preview regeneration...');

        // Query for PDF files
        $query = File::where('fileExtension', 'pdf');

        // Filter by type if specified
        if ($type) {
            if (! in_array($type, ['receipt', 'document'])) {
                $this->error('Invalid type. Must be "receipt" or "document".');

                return Command::FAILURE;
            }
            $query->where('file_type', $type);
        }

        if (! $force) {
            $query->where(function ($q) {
                $q->whereRaw('has_image_preview = false')
                    ->orWhereNull('has_image_preview');
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $files = $query->get();
        $total = $files->count();

        if ($total === 0) {
            $this->info('No PDF files found that need preview generation.');

            return Command::SUCCESS;
        }

        $this->info("Found {$total} PDF files to process.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $successful = 0;
        $failed = 0;

        foreach ($files as $file) {
            try {
                // Get the local file path
                $localPath = storage_path('app/uploads/'.$file->guid.'.pdf');

                // Download from S3 if not local
                if (! file_exists($localPath)) {
                    $this->downloadFromS3($file, $localPath);
                }

                if (file_exists($localPath)) {
                    $result = $previewManager->generatePreviewForFile($file, $localPath);

                    if ($result) {
                        $successful++;
                        Log::info('[RegenerateImagePreviews] Preview generated', [
                            'file_id' => $file->id,
                            'guid' => $file->guid,
                        ]);
                    } else {
                        $failed++;
                        Log::warning('[RegenerateImagePreviews] Preview generation failed', [
                            'file_id' => $file->id,
                            'guid' => $file->guid,
                        ]);
                    }

                    // Clean up local file
                    if (file_exists($localPath)) {
                        unlink($localPath);
                    }
                } else {
                    $failed++;
                    Log::error('[RegenerateImagePreviews] Could not access file', [
                        'file_id' => $file->id,
                        'guid' => $file->guid,
                    ]);
                }
            } catch (Exception $e) {
                $failed++;
                Log::error('[RegenerateImagePreviews] Exception processing file', [
                    'file_id' => $file->id,
                    'guid' => $file->guid,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Preview regeneration complete!');
        $this->info("Successful: {$successful}");
        $this->warn("Failed: {$failed}");

        return Command::SUCCESS;
    }

    /**
     * Download file from S3 to local path.
     */
    private function downloadFromS3(File $file, string $localPath): void
    {
        $s3Path = $file->s3_original_path ?? $file->s3_processed_path;

        if (! $s3Path) {
            // Try to build the path
            $s3Path = "receipts/{$file->user_id}/{$file->guid}/original.pdf";
        }

        $disk = Storage::disk('paperpulse');

        if ($disk->exists($s3Path)) {
            $content = $disk->get($s3Path);

            // Ensure directory exists
            $dir = dirname($localPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($localPath, $content);
        }
    }
}
