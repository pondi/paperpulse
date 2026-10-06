<?php

use App\Jobs\Files\ClassifyFile;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

it('accepts an upload without requiring the user to choose a receipt or document type', function (): void {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    Bus::fake();
    config(['ai.file_processing_provider' => 'textract+openai']);
    $owner = User::factory()->create();
    Sanctum::actingAs($owner);
    $this->post(route('api.files.store'), ['file' => UploadedFile::fake()->image('scan.jpg')])->assertCreated();
    expect(File::withoutGlobalScope('user')->where('user_id', $owner->id)->sole()->file_type)->toBe('document');
    Bus::assertChained([ProcessFile::class, ClassifyFile::class, DeleteWorkingFiles::class]);
});
