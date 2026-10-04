<?php

use App\Services\Documents\ConversionService;
use App\Services\Documents\LocalOfficeConverter;
use App\Services\Files\StoragePathBuilder;
use Illuminate\Support\Facades\Storage;

it('converts owned office documents using a standard database queue worker', function (): void {
    Storage::fake('paperpulse');
    $file = createOfficeConversionFile();
    Storage::disk('paperpulse')->put($file->s3_original_path, conversionDocxFixture());
    $output = StoragePathBuilder::storagePath($file->user_id, $file->guid, 'document', 'archive', 'pdf');
    $this->mock(LocalOfficeConverter::class)->shouldReceive('convert')->once()
        ->andReturnUsing(fn ($source, $destination) => file_put_contents($destination, conversionPdfFixture()));
    $conversion = app(ConversionService::class)->queueConversion($file, $file->s3_original_path, $output);
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'conversions', '--once' => true, '--no-interaction' => true])->assertSuccessful();
    expect($conversion->fresh()->isCompleted())->toBeTrue();
    $this->assertDatabaseCount('failed_jobs', 0);
    Storage::disk('paperpulse')->assertExists($output);
});
