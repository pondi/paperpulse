<?php

declare(strict_types=1);

use App\Jobs\BankStatements\ProcessCsvImport;
use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Documents\ProcessDocument;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Files\ProcessFileGemini;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Jobs\Receipts\MatchMerchant;
use App\Jobs\Receipts\ProcessReceipt;
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
});

// --- Gemini pipeline ---

it('dispatches Gemini pipeline for receipts', function () {
    config(['ai.file_processing_provider' => 'gemini']);

    Cache::put("job.{$this->jobId}.fileMetaData", [
        'fileExtension' => 'jpg',
        'fileId' => 1,
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
        'fileId' => 1,
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
        'fileId' => 1,
        'jobName' => 'TestJob',
        'metadata' => ['source' => 'upload'],
    ], 3600);

    $this->dispatcher->dispatch($this->jobId, 'receipt');

    Bus::assertChained([
        ProcessFile::class,
        ProcessReceipt::class,
        MatchMerchant::class,
        DeleteWorkingFiles::class,
    ]);
});

it('dispatches legacy document pipeline', function () {
    config(['ai.file_processing_provider' => 'textract+openai']);

    Cache::put("job.{$this->jobId}.fileMetaData", [
        'fileExtension' => 'pdf',
        'fileId' => 1,
        'jobName' => 'TestJob',
        'metadata' => ['source' => 'upload'],
    ], 3600);

    $this->dispatcher->dispatch($this->jobId, 'document');

    Bus::assertChained([
        ProcessFile::class,
        ProcessDocument::class,
        AnalyzeDocument::class,
        DeleteWorkingFiles::class,
    ]);
});

// --- CSV bank statement pipeline ---

it('routes CSV files to bank statement import pipeline', function () {
    Cache::put("job.{$this->jobId}.fileMetaData", [
        'fileExtension' => 'csv',
        'fileId' => 42,
        'jobName' => 'TestCSVJob',
        'metadata' => ['source' => 'upload'],
    ], 3600);

    $this->dispatcher->dispatch($this->jobId, 'document');

    Bus::assertChained([
        ProcessCsvImport::class,
        DeleteWorkingFiles::class,
    ]);
});
