<?php

use App\Models\Document;
use App\Models\File;
use App\Models\User;
use App\Services\Documents\DocumentTransformer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Document::disableSearchSyncing();
});

afterEach(function () {
    Document::enableSearchSyncing();
});

it('treats a document as PDF when an archive PDF exists', function () {
    $user = User::factory()->create();

    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
        'processing_type' => 'document',
        'fileExtension' => 'docx',
        'fileType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        's3_archive_path' => 'documents/'.$user->id.'/example/archive.pdf',
    ]);

    $document = Document::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'title' => 'Example Document',
        'document_type' => 'other',
    ])->load('file');

    $indexData = DocumentTransformer::forIndex($document);
    expect($indexData['file']['is_pdf'])->toBeTrue();
    expect($indexData['file']['pdfUrl'])->toContain('variant=archive');

    $showData = DocumentTransformer::forShow($document);
    expect($showData['file']['is_pdf'])->toBeTrue();
    expect($showData['file']['pdfUrl'])->toContain('variant=archive');
});

it('does not treat a non-pdf document as PDF without an archive', function () {
    $user = User::factory()->create();

    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
        'processing_type' => 'document',
        'fileExtension' => 'jpg',
        'fileType' => 'image/jpeg',
        's3_archive_path' => null,
    ]);

    $document = Document::factory()->create([
        'user_id' => $user->id,
        'file_id' => $file->id,
        'title' => 'Example Image Document',
        'document_type' => 'other',
    ])->load('file');

    $indexData = DocumentTransformer::forIndex($document);
    expect($indexData['file']['is_pdf'])->toBeFalse();
    expect($indexData['file']['pdfUrl'])->toBeNull();
});
