<?php

use App\Services\OCR\Textract\TextractPdfImageProcessor;
use Aws\Result;
use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Storage;

function processingPdfFixture(): string
{
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Contents 5 0 R >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Contents 6 0 R >>',
        "<< /Length 0 >>\nstream\n\nendstream",
        "<< /Length 0 >>\nstream\n\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 7\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf."trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
}

it('renders each real PDF page to the actual saved path and supplies images to OCR', function () {
    if (! extension_loaded('imagick') || ! exec('which gs')) {
        $this->markTestSkipped('Imagick and Ghostscript are required');
    }
    Storage::fake('textract');
    Storage::disk('textract')->put('source.pdf', processingPdfFixture());
    config(['ai.ocr.options.pdf_image_max_pages' => 3]);
    $client = Mockery::mock(TextractClient::class);
    $page = 0;
    $client->shouldReceive('analyzeDocument')->twice()->andReturnUsing(function (array $request) use (&$page): Result {
        $path = $request['Document']['S3Object']['Name'];
        expect(getimagesizefromstring(Storage::disk('textract')->get($path))['mime'])->toBe('image/jpeg');

        return new Result(['Blocks' => [['BlockType' => 'LINE', 'Text' => 'Page '.++$page, 'Confidence' => 99]]]);
    });
    $result = TextractPdfImageProcessor::process($client, 'bucket', 'source.pdf');
    expect($result['text'])->toContain('Page 1')->toContain('Page 2')
        ->and($result['metadata']['original_pdf_pages'])->toBe(2)
        ->and(Storage::disk('textract')->allFiles())->toBe(['source.pdf']);
});

it('cleans page images and partial local output after OCR fails', function () {
    if (! extension_loaded('imagick') || ! exec('which gs')) {
        $this->markTestSkipped('Imagick and Ghostscript are required');
    }
    Storage::fake('textract');
    Storage::disk('textract')->put('failure.pdf', processingPdfFixture());
    $client = Mockery::mock(TextractClient::class);
    $client->shouldReceive('analyzeDocument')->once()->andThrow(new RuntimeException('OCR unavailable'));
    expect(fn () => TextractPdfImageProcessor::process($client, 'bucket', 'failure.pdf'))->toThrow(RuntimeException::class)
        ->and(glob(storage_path('app/temp/failure*')))->toBe([])
        ->and(Storage::disk('textract')->allFiles())->toBe(['failure.pdf']);
});
