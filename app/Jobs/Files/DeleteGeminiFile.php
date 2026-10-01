<?php

namespace App\Jobs\Files;

use App\Services\AI\FileManager\GeminiFileManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class DeleteGeminiFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public string $resourceName) {}

    public function handle(GeminiFileManager $files): void
    {
        if (! $files->deleteFile($this->resourceName)) {
            throw new RuntimeException('Gemini file cleanup failed');
        }
    }
}
