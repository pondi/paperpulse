<?php

use App\Services\OCR\Providers\TextractProvider;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\Textract\TextractClient;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->provider = app(TextractProvider::class);
    $this->client = Mockery::mock(TextractClient::class);
    (new ReflectionProperty($this->provider, 'client'))->setValue($this->provider, $this->client);
    $this->pdfPath = sys_get_temp_dir().'/'.Str::uuid().'.pdf';
    file_put_contents($this->pdfPath, conversionPdfFixture());
});

afterEach(function (): void {
    unlink($this->pdfPath);
});

it('extracts text from a pdf using textract', function (): void {
    $this->client->shouldReceive('detectDocumentText')->once()
        ->with(['Document' => ['Bytes' => file_get_contents($this->pdfPath)]])
        ->andReturn(new Result(['Blocks' => [
            ['BlockType' => 'LINE', 'Text' => 'Fixture document', 'Confidence' => 99],
        ]]));

    $result = $this->provider->extractText($this->pdfPath, 'document', 'textract-fixture');

    expect($result->success)->toBeTrue()
        ->and($result->provider)->toBe('textract')
        ->and($result->text)->toBe('Fixture document')
        ->and($result->confidence)->toBe(0.99);
});

it('reports textract service failures without treating them as successful extraction', function (): void {
    $this->client->shouldReceive('detectDocumentText')->once()
        ->andThrow(new AwsException('Service unavailable', new Command('DetectDocumentText'), [
            'message' => 'Fixture service failure',
        ]));

    $result = $this->provider->extractText($this->pdfPath, 'document', 'textract-fixture');

    expect($result->success)->toBeFalse()
        ->and($result->provider)->toBe('textract')
        ->and($result->error)->toBe('Fixture service failure');
});

it('rejects an empty pdf before contacting textract', function (): void {
    file_put_contents($this->pdfPath, '');
    $this->client->shouldNotReceive('detectDocumentText');

    $result = $this->provider->extractText($this->pdfPath, 'document', 'textract-fixture');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toBe('File is empty');
});
