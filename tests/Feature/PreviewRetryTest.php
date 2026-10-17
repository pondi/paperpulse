<?php

use App\Jobs\Files\GenerateFilePreview;
use App\Jobs\Files\ProcessFileGemini;
use App\Models\File;
use App\Services\Files\FilePreviewManager;
use App\Services\Files\StoragePathBuilder;
use App\Services\Workers\WorkerFileManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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

it('repairs an inconsistent preview flag without a stored preview path', function (): void {
    Queue::fake();
    $file = File::factory()->create([
        'status' => 'completed', 'fileExtension' => 'pdf',
        's3_original_path' => 'source.pdf', 'has_image_preview' => true, 's3_image_path' => null,
    ]);
    $this->actingAs($file->user)->getJson(route('files.show', $file))->assertOk()
        ->assertJsonPath('file.has_preview', false)->assertJsonPath('file.preview_pending', true);
    Queue::assertPushed(GenerateFilePreview::class, fn (GenerateFilePreview $job): bool => $job->fileId === $file->id);

    $manager = Mockery::mock(FilePreviewManager::class);
    $manager->shouldReceive('generatePreviewForFile')->once()->withArgs(fn (File $source, string $path): bool => $source->id === $file->id && $path === '/working/source.pdf')->andReturnTrue();
    $workers = Mockery::mock(WorkerFileManager::class);
    $workers->shouldReceive('ensureLocalFile')->once()->andReturn('/working/source.pdf');
    $workers->shouldReceive('cleanupLocalFile')->once();
    (new GenerateFilePreview($file->id))->handle($manager, $workers);
    expect(Cache::get('file-preview-repair:'.$file->id))->toBeFalse();
});

it('generates stores and serves a usable JPEG preview independently of the original', function (): void {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick is unavailable');
    }
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    $image = new Imagick;
    $image->newImage(40, 24, 'white', 'png');
    $path = tempnam(sys_get_temp_dir(), 'generated-preview-').'.png';
    $image->writeImage($path);
    $image->clear();
    $file = File::factory()->create([
        'status' => 'completed', 'fileExtension' => 'png', 'file_type' => 'document',
        's3_original_path' => 'assets/original.png', 'has_image_preview' => false, 's3_image_path' => null,
    ]);
    try {
        expect(app(FilePreviewManager::class)->generatePreviewForFile($file, $path))->toBeTrue();
        $file->refresh();
        expect($file->has_image_preview)->toBeTrue()
            ->and($file->s3_image_path)->toBe(StoragePathBuilder::previewPath($file->user_id, $file->guid, 'document'))
            ->and($file->image_generation_error)->toBeNull();
        $response = $this->actingAs($file->user)->get(route('documents.serve', [
            'guid' => $file->guid, 'type' => 'preview', 'extension' => 'jpg',
        ]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $dimensions = getimagesizefromstring($response->streamedContent());
        expect($dimensions['mime'])->toBe('image/jpeg')
            ->and($dimensions[0])->toBe(40)->and($dimensions[1])->toBe(24);
    } finally {
        unlink($path);
    }
});
