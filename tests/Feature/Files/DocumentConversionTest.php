<?php

use App\Models\File;
use App\Models\User;
use App\Services\Documents\ConversionService;
use App\Services\Files\StoragePathBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('converts an html document to pdf via the conversion pipeline', function () {
    // Skip: Requires Gotenberg external service
    $this->markTestSkipped('Requires Gotenberg external service to be running');
    $user = User::factory()->create();
    $guid = 'conversion-fixture-'.uniqid();

    $file = File::factory()->create([
        'user_id' => $user->id,
        'guid' => $guid,
        'fileName' => 'fixture.html',
        'fileExtension' => 'html',
        'fileType' => 'text/html',
        'file_type' => 'document',
        'status' => 'pending',
    ]);

    $inputPath = StoragePathBuilder::storagePath($user->id, $guid, 'document', 'original', 'html');
    $outputPath = StoragePathBuilder::storagePath($user->id, $guid, 'document', 'archive', 'pdf');

    $htmlPath = createFixtureHtmlPath();
    Storage::disk('paperpulse')->put($inputPath, file_get_contents($htmlPath));
    $file->update(['s3_original_path' => $inputPath]);

    $conversionService = app(ConversionService::class);
    $conversion = $conversionService->queueConversion($file, $inputPath, $outputPath);
    $result = $conversionService->waitForCompletion($conversion, 180);

    expect($result['success'])->toBeTrue();
    expect(Storage::disk('paperpulse')->exists($outputPath))->toBeTrue();
});
