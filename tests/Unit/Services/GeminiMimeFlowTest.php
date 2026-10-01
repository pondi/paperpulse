<?php

use App\Exceptions\GeminiApiException;
use App\Models\File;
use App\Services\AI\Extractors\Receipt\ReceiptExtractor;
use App\Services\AI\FileManager\GeminiFileManager;
use App\Services\AI\FileManager\GeminiMimeType;
use App\Services\AI\TypeClassification\GeminiTypeClassifier;
use Illuminate\Support\Facades\Http;

it('carries detected MIME through upload classification and real extraction adapter', function (string $extension, string $mime) {
    config(['ai.providers.gemini.api_key' => 'fake-key']);
    $path = tempnam(sys_get_temp_dir(), 'mime_').'.'.$extension;
    if (in_array($extension, ['png', 'jpg'], true)) {
        $image = new Imagick;
        $image->newImage(10, 10, 'white', $extension);
        $image->writeImage($path);
    } else {
        file_put_contents($path, $extension === 'pdf' ? "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF" : 'Receipt Shop total 10 date 2024-01-01');
    }
    $calls = 0;
    Http::fake(function ($request) use ($mime, &$calls) {
        if (str_contains($request->url(), ':countTokens')) {
            return Http::response(['totalTokens' => 100]);
        }
        if (str_contains($request->url(), '/upload/')) {
            expect($request->header('X-Goog-Upload-Header-Content-Type'))->toBe([$mime]);

            return Http::response([], 200, ['X-Goog-Upload-URL' => 'https://upload.test/file']);
        }
        if ($request->url() === 'https://upload.test/file') {
            return Http::response(['file' => ['uri' => 'https://gemini.test/file', 'name' => 'files/abc', 'mimeType' => $mime]]);
        }
        $part = $request['contents'][0]['parts'][1]['fileData'];
        expect($part['mimeType'])->toBe($mime);
        $data = ++$calls === 1 ? ['document_type' => 'receipt', 'confidence' => 0.99, 'reasoning' => 'Receipt']
            : ['merchant_name' => 'Shop', 'total_amount' => 10, 'receipt_date' => '2024-01-01', 'description' => 'Purchase', 'category' => 'Groceries'];

        return Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($data)]]]]]]);
    });
    try {
        $reference = (new GeminiFileManager)->uploadFile($path);
        $classification = app(GeminiTypeClassifier::class)->classify($reference['fileUri'], ['mime_type' => $reference['mimeType']]);
        $entity = app(ReceiptExtractor::class)->extract($reference['fileUri'], new File, ['mime_type' => $reference['mimeType']]);
        expect($classification->type)->toBe('receipt')->and($entity['data']['totals']['total_amount'])->toBe(10);
    } finally {
        unlink($path);
    }
})->with([['pdf', 'application/pdf'], ['png', 'image/png'], ['jpg', 'image/jpeg'], ['txt', 'text/plain']]);

it('rejects unsupported and mismatched MIME before submission', function () {
    Http::fake();
    $path = tempnam(sys_get_temp_dir(), 'mime_').'.pdf';
    file_put_contents($path, 'This is plain text with a PDF extension');
    try {
        expect(fn () => GeminiMimeType::detect($path))->toThrow(GeminiApiException::class);
    } finally {
        unlink($path);
    }
    expect(fn () => GeminiMimeType::validate('application/zip'))->toThrow(GeminiApiException::class);
    Http::assertNothingSent();
});
