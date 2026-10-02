<?php

use App\Http\Requests\Api\V1\StoreFileRequest;
use App\Models\User;
use App\Services\Documents\DocumentUploadValidator;
use App\Services\File\FileValidationService;
use App\Services\Files\FileUploadConfigService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    config()->set('ai.file_processing_provider', 'textract+openai');
});

it('rejects spoofed MIME and size declarations at every byte validation entrypoint', function (): void {
    $service = app(FileValidationService::class);
    $file = UploadedFile::fake()->createWithContent('spoof.pdf', 'plain text');
    expect($service->validateUploadedFile($file, 'document')['valid'])->toBeFalse()
        ->and(DocumentUploadValidator::validate($file)['valid'])->toBeFalse();
    $request = new StoreFileRequest(['file_type' => 'document']);
    expect(Validator::make(['file' => $file, 'file_type' => 'document'], $request->rules())->fails())->toBeTrue();
    $png = file_get_contents(createFixturePngPath());
    expect($service->validateFileData(['fileName' => 'image.png', 'extension' => 'png', 'size' => 1, 'content' => $png], 'receipt')['valid'])->toBeFalse();
});

it('accepts document images and verified office containers consistently', function (): void {
    foreach (['image.png' => file_get_contents(createFixturePngPath()), 'document.docx' => conversionDocxFixture()] as $name => $bytes) {
        $file = UploadedFile::fake()->createWithContent($name, $bytes);
        $data = ['fileName' => $name, 'extension' => pathinfo($name, PATHINFO_EXTENSION), 'size' => strlen($bytes), 'content' => $bytes];
        expect(app(FileValidationService::class)->validateFileData($data, 'document')['valid'])->toBeTrue()
            ->and(DocumentUploadValidator::validate($file)['valid'])->toBeTrue();
        $request = new StoreFileRequest(['file_type' => 'document']);
        expect(Validator::make(['file' => $file, 'file_type' => 'document'], $request->rules())->fails())->toBeFalse();
    }
});

it('rejects invalid office containers empty files and mismatched extensions', function (): void {
    $service = app(FileValidationService::class);
    foreach ([['bad.docx', 'docx', 'not a zip'], ['bad.png', 'png', ''], ['bad.pdf', 'png', file_get_contents(createFixturePngPath())]] as [$name, $extension, $content]) {
        expect($service->validateFileData(['fileName' => $name, 'extension' => $extension, 'size' => strlen($content), 'content' => $content], 'document')['valid'])->toBeFalse();
    }
});

it('shares configured provider converter UI and Forge limits', function (): void {
    config()->set('ai.file_processing_provider', 'gemini');
    $config = app(FileUploadConfigService::class);
    expect($config->getMaxSizeMb('document'))->toBe(50)
        ->and($config->getCapabilities('document')['docx']['maxBytes'])->toBe(20 * 1024 * 1024);
    config()->set('processing.conversion.enabled', false);
    expect($config->getCapabilities('document'))->not->toHaveKey('docx');
    config()->set('ai.file_processing_provider', 'textract+openai');
    expect($config->getMaxSizeMb('receipt'))->toBe(10)
        ->and($config->getCapabilities('document'))->not->toHaveKey('txt');
    $this->artisan('uploads:limits')->expectsOutput('upload_max_filesize = 10M')->expectsOutput('post_max_size = 201M')->assertSuccessful();
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('documents.upload'))->assertInertia(fn ($page) => $page->where('uploadConfig', $config->getUploadConfig()));
});

it('rejects manifests beyond the effective provider and converter limits', function (): void {
    $this->actingAs(User::factory()->create());
    $this->postJson('/api/v1/bulk/sessions', ['file_type' => 'receipt', 'files' => [[
        'filename' => 'large.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
        'size' => 11 * 1024 * 1024, 'hash' => hash('sha256', 'large'),
    ]]])->assertUnprocessable()->assertJsonValidationErrors('files.0.size');
});
