<?php

namespace App\Jobs\Files;

use App\Models\File;
use App\Services\Files\FilePreviewManager;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class GenerateFilePreview implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $fileId) {}

    public function handle(FilePreviewManager $previews, WorkerFileManager $files): void
    {
        $file = File::query()->find($this->fileId);
        if ($file === null || $file->has_image_preview) {
            return;
        }
        $path = $files->ensureLocalFile($file->s3_original_path, $file->guid, $file->fileExtension);
        try {
            if (! $previews->generatePreviewForFile($file, $path)) {
                throw new RuntimeException($file->image_generation_error ?? 'Preview generation failed');
            }
        } finally {
            $files->cleanupLocalFile($path, $file->guid, 'GenerateFilePreview');
        }
    }
}
