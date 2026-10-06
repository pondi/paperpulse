<?php

declare(strict_types=1);

use App\Jobs\BankStatements\ProcessCsvImport;
use App\Jobs\Files\ClassifyFile;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Files\ProcessFileGemini;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Models\File;
use App\Services\Files\FileJobChainDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    $this->dispatcher = new FileJobChainDispatcher;
    $this->jobId = (string) Str::uuid();
    $this->file = File::factory()->create();
});

// --- Gemini pipeline ---

it('dispatches Gemini pipeline for receipts', function () {
    config(['ai.file_processing_provider' => 'gemini']);

    Cache::put("job.{$this->jobId}.fileMetaData", [
        'fileExtension' => 'jpg',
        'fileId' => $this->file->id,
        'jobName' => 'TestJob',
        'metadata' => ['source' => 'upload'],
    ], 3600);

    $this->dispatcher->dispatch($this->jobId, 'receipt');

    Bus::assertChained([
        ProcessFile::class,
        ProcessFileGemini::class,
        DeleteWorkingFiles::class,
    ]);
});

it('dispatches Gemini pipeline for documents', function () {
    config(['ai.file_processing_provider' => 'gemini']);

    Cache::put("job.{$this->jobId}.fileMetaData", [
        'fileExtension' => 'pdf',
        'fileId' => $this->file->id,
        'jobName' => 'TestJob',
        'metadata' => ['source' => 'upload'],
    ], 3600);

    $this->dispatcher->dispatch($this->jobId, 'document');

    Bus::assertChained([
        ProcessFile::class,
        ProcessFileGemini::class,
        DeleteWorkingFiles::class,
    ]);
});

// --- Legacy textract+openai pipeline ---

it('dispatches legacy receipt pipeline', function () {
    config(['ai.file_processing_provider' => 'textract+openai']);

    Cache::put("job.{$this->jobId}.fileMetaData", [
        'fileExtension' => 'jpg',
        'fileId' => $this->file->id,
        'jobName' => 'TestJob',
        'metadata' => ['source' => 'upload'],
    ], 3600);

    $this->dispatcher->dispatch($this->jobId, 'receipt');

    Bus::assertChained([
        ProcessFile::class,
        ClassifyFile::class,
        DeleteWorkingFiles::class,
    ]);
});

it('dispatches legacy document pipeline', function () {
    config(['ai.file_processing_provider' => 'textract+openai']);

    Cache::put("job.{$this->jobId}.fileMetaData", [
        'fileExtension' => 'pdf',
        'fileId' => $this->file->id,
        'jobName' => 'TestJob',
        'metadata' => ['source' => 'upload'],
    ], 3600);

    $this->dispatcher->dispatch($this->jobId, 'document');

    Bus::assertChained([
        ProcessFile::class,
        ClassifyFile::class,
        DeleteWorkingFiles::class,
    ]);
});

// --- CSV bank statement pipeline ---

it('routes CSV files to bank statement import pipeline', function () {
    Cache::put("job.{$this->jobId}.fileMetaData", [
        'fileExtension' => 'csv',
        'fileId' => $this->file->id,
        'jobName' => 'TestCSVJob',
        'metadata' => ['source' => 'upload'],
    ], 3600);

    $this->dispatcher->dispatch($this->jobId, 'document');

    Bus::assertChained([
        ProcessCsvImport::class,
        DeleteWorkingFiles::class,
    ]);
});
