<?php

use App\Jobs\Documents\ProcessDocument;

it('extracts valid document dates without a namespaced return type', function (string $text, ?string $expected) {
    $job = new class('document-date') extends ProcessDocument
    {
        public function dateFromText(string $text): ?DateTimeInterface
        {
            return $this->extractDocumentDate($text);
        }
    };

    expect($job->dateFromText($text)?->format('Y-m-d'))->toBe($expected);
})->with([
    ['Document dated 2024-02-29.', '2024-02-29'],
    ['Issued 31.12.2024', '2024-12-31'],
    ['Issued 31/12/2024', '2024-12-31'],
    ['Issued 2024-02-30', null],
    ['Issued 03/04/2024', null],
    ['No date recorded', null],
]);
