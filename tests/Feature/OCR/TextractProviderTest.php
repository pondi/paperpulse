<?php

use App\Services\OCR\Providers\TextractProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('extracts text from a pdf using textract', function () {
    // Skip: Requires real AWS Textract credentials and service access
    $this->markTestSkipped('Requires AWS Textract service with valid credentials');

    $provider = app(TextractProvider::class);
    $pdfPath = createFixturePdfPath();

    $result = $provider->extractText($pdfPath, 'document', 'textract-fixture');

    expect($result->success)->toBeTrue();
    expect($result->provider)->toBe('textract');
});
