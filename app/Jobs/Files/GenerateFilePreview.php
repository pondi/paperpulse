<?php

namespace App\Jobs\Files;

use App\Models\File;
use App\Services\Files\FilePreviewManager;
use App\Services\Files\StoragePathBuilder;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class GenerateFilePreview implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $timeout = 120;

    public int $uniqueFor = 900;

    public function __construct(public int $fileId)
    {
        $this->onConnection('database')->onQueue('files');
    }

    public function uniqueId(): string
    {
        return (string) $this->fileId;
    }

    public function handle(FilePreviewManager $previews, WorkerFileManager $files): void
    {
        $file = File::withoutGlobalScope('user')->find($this->fileId);
        if ($file === null || $file->has_image_preview) {
            Cache::put('file-preview-repair:'.$this->fileId, false, now()->addMinutes(30));

            return;
        }
        $pdfVariant = StoragePathBuilder::pdfVariant($file);
        $source = StoragePathBuilder::variantPath($file, $pdfVariant ?? 'original');
        if (! $source) {
            throw new RuntimeException('Preview source is unavailable');
        }
        $path = $files->ensureLocalFile($source, $file->guid, $pdfVariant ? 'pdf' : $file->fileExtension, jobId: 'preview-'.$file->id);
        try {
            if (! $previews->generatePreviewForFile($file, $path)) {
                throw new RuntimeException($file->image_generation_error ?? 'Preview generation failed');
            }
            Cache::put('file-preview-repair:'.$this->fileId, false, now()->addMinutes(30));
        } finally {
            $files->cleanupLocalFile($path, $file->guid, 'GenerateFilePreview');
        }
    }

    public function failed(Throwable $exception): void
    {
        Cache::put('file-preview-repair:'.$this->fileId, false, now()->addMinutes(30));
    }
}
