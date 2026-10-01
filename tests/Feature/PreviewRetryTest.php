<?php

use App\Jobs\Files\GenerateFilePreview;
use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Services\Files\FilePreviewManager;
use App\Services\Workers\WorkerFileManager;

it('creates a real image preview through the Gemini thumbnail path', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick is unavailable');
    }
    $image = new Imagick;
    $image->newImage(10, 10, 'red', 'png');
    $path = tempnam(sys_get_temp_dir(), 'preview_').'.png';
    $image->writeImage($path);
    $job = new class('preview-test') extends ProcessFileGemini
    {
        public function thumbnail(string $path): ?string
        {
            return $this->generateThumbnail($path, 'preview-test');
        }
    };
    try {
        $data = base64_decode($job->thumbnail($path));
        expect(getimagesizefromstring($data)['mime'])->toBe('image/jpeg');
    } finally {
        unlink($path);
    }
});

it('retains preview errors for independent retries without failing extraction', function () {
    $file = File::factory()->create(['status' => 'completed', 's3_original_path' => 'source.png']);
    $manager = app(FilePreviewManager::class);
    expect($manager->generatePreviewForFile($file, '/missing-preview.png'))->toBeFalse()
        ->and($file->fresh()->status)->toBe('completed')
        ->and($file->image_generation_error)->toContain('Image file not found');
    $workers = Mockery::mock(WorkerFileManager::class);
    $workers->shouldReceive('ensureLocalFile')->once()->andReturn('/missing-preview.png');
    $workers->shouldReceive('cleanupLocalFile')->once();
    expect(fn () => (new GenerateFilePreview($file->id))->handle($manager, $workers))->toThrow(RuntimeException::class);
});
