<?php

declare(strict_types=1);

use App\Contracts\Services\FileDuplicationContract;
use App\Contracts\Services\FileMetadataContract;
use App\Contracts\Services\FileStorageContract;
use App\Contracts\Services\FileValidationContract;
use App\Exceptions\DuplicateFileException;
use App\Models\File;
use App\Models\User;
use App\Services\FileProcessingService;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\TextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->fileStorage = Mockery::mock(FileStorageContract::class);
    $this->fileMetadata = Mockery::mock(FileMetadataContract::class);
    $this->fileValidation = Mockery::mock(FileValidationContract::class);
    $this->fileDuplication = Mockery::mock(FileDuplicationContract::class);
    $this->textExtraction = Mockery::mock(TextExtractionService::class);
    $this->jobChainDispatcher = Mockery::mock(FileJobChainDispatcher::class);

    $this->service = new FileProcessingService(
        $this->fileStorage,
        $this->fileMetadata,
        $this->fileValidation,
        $this->fileDuplication,
        $this->textExtraction,
        $this->jobChainDispatcher,
    );

    $this->user = User::factory()->create();
});

// --- processFile: happy path ---

it('processes a file successfully through the full pipeline', function () {
    $fileData = [
        'content' => 'file-content',
        'fileName' => 'receipt.jpg',
        'extension' => 'jpg',
        'size' => 1024,
        'source' => 'upload',
    ];

    $file = File::factory()->create(['user_id' => $this->user->id]);

    $this->fileValidation->shouldReceive('validateFileData')
        ->once()
        ->with($fileData, 'receipt')
        ->andReturn(['valid' => true]);

    $this->fileDuplication->shouldReceive('checkDuplication')
        ->once()
        ->with('file-content', $this->user->id)
        ->andReturn(['isDuplicate' => false, 'hash' => 'abc123', 'existingFile' => null]);

    $this->fileStorage->shouldReceive('generateFileGuid')
        ->once()
        ->andReturn('test-guid');

    $this->fileMetadata->shouldReceive('generateJobName')
        ->once()
        ->andReturn('TestJob-001');

    $this->fileStorage->shouldReceive('storeWorkingContent')
        ->once()
        ->with('file-content', 'test-guid', 'jpg')
        ->andReturn('/tmp/test-guid.jpg');

    $this->fileMetadata->shouldReceive('createFileRecordFromData')
        ->once()
        ->andReturn($file);

    $this->fileStorage->shouldReceive('storeToS3')
        ->once()
        ->andReturn('s3://bucket/path/receipt.jpg');

    $this->fileMetadata->shouldReceive('updateFileWithS3Path')
        ->once()
        ->with($file, 's3://bucket/path/receipt.jpg');

    // deleteWorkingFile may or may not be called depending on file_exists() check
    $this->fileStorage->shouldReceive('deleteWorkingFile')
        ->zeroOrMoreTimes()
        ->andReturn(true);

    $this->fileMetadata->shouldReceive('prepareFileMetadata')
        ->once()
        ->andReturn(['fileId' => $file->id, 'jobName' => 'TestJob-001']);

    $this->jobChainDispatcher->shouldReceive('dispatch')
        ->once();

    $result = $this->service->processFile($fileData, 'receipt', $this->user->id);

    expect($result['success'])->toBeTrue();
    expect($result['fileGuid'])->toBe('test-guid');
    expect($result['jobName'])->toBe('TestJob-001');
    expect($result['fileId'])->toBe($file->id);
});

// --- processFile: validation failure ---

it('throws exception when file validation fails', function () {
    $fileData = [
        'content' => 'bad-content',
        'fileName' => 'bad.exe',
        'extension' => 'exe',
        'size' => 1024,
    ];

    $this->fileStorage->shouldReceive('generateFileGuid')->andReturn('guid');
    $this->fileMetadata->shouldReceive('generateJobName')->andReturn('Job');

    $this->fileValidation->shouldReceive('validateFileData')
        ->once()
        ->andReturn(['valid' => false, 'errors' => ['Unsupported file type']]);

    $this->service->processFile($fileData, 'receipt', $this->user->id);
})->throws(Exception::class, 'File validation failed: Unsupported file type');

// --- processFile: duplicate detection ---

it('throws DuplicateFileException when file is a duplicate', function () {
    $existingFile = File::factory()->create(['user_id' => $this->user->id]);

    $fileData = [
        'content' => 'duplicate-content',
        'fileName' => 'dup.jpg',
        'extension' => 'jpg',
        'size' => 512,
    ];

    $this->fileStorage->shouldReceive('generateFileGuid')->andReturn('guid');
    $this->fileMetadata->shouldReceive('generateJobName')->andReturn('Job');

    $this->fileValidation->shouldReceive('validateFileData')
        ->andReturn(['valid' => true]);

    $this->fileDuplication->shouldReceive('checkDuplication')
        ->once()
        ->andReturn([
            'isDuplicate' => true,
            'hash' => 'dup-hash',
            'existingFile' => $existingFile,
        ]);

    $this->service->processFile($fileData, 'receipt', $this->user->id);
})->throws(DuplicateFileException::class);

// --- processFile: uses provided metadata ---

it('uses provided jobId and jobName from metadata', function () {
    $fileData = [
        'content' => 'content',
        'fileName' => 'doc.pdf',
        'extension' => 'pdf',
        'size' => 2048,
        'source' => 'pulsedav',
    ];

    $file = File::factory()->create(['user_id' => $this->user->id]);

    $this->fileStorage->shouldReceive('generateFileGuid')->andReturn('guid');

    $this->fileValidation->shouldReceive('validateFileData')
        ->andReturn(['valid' => true]);

    $this->fileDuplication->shouldReceive('checkDuplication')
        ->andReturn(['isDuplicate' => false, 'hash' => 'hash', 'existingFile' => null]);

    $this->fileStorage->shouldReceive('storeWorkingContent')->andReturn('/tmp/guid.pdf');
    $this->fileMetadata->shouldReceive('createFileRecordFromData')->andReturn($file);
    $this->fileStorage->shouldReceive('storeToS3')->andReturn('s3://path');
    $this->fileMetadata->shouldReceive('updateFileWithS3Path');
    $this->fileStorage->shouldReceive('deleteWorkingFile')->andReturn(true);
    $this->fileMetadata->shouldReceive('prepareFileMetadata')->andReturn([]);
    $this->jobChainDispatcher->shouldReceive('dispatch')->once();

    $result = $this->service->processFile($fileData, 'document', $this->user->id, [
        'jobId' => 'custom-job-id',
        'jobName' => 'CustomJobName',
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['jobId'])->toBe('custom-job-id');
    expect($result['jobName'])->toBe('CustomJobName');
});

// --- processUpload ---

it('processes an uploaded file via processUpload', function () {
    $uploadedFile = UploadedFile::fake()->image('photo.jpg', 100, 100);
    $file = File::factory()->create(['user_id' => $this->user->id]);

    $this->fileValidation->shouldReceive('validateUploadedFile')
        ->once()
        ->andReturn(['valid' => true]);

    $this->fileMetadata->shouldReceive('extractFileDataFromUpload')
        ->once()
        ->andReturn([
            'content' => 'img-data',
            'fileName' => 'photo.jpg',
            'extension' => 'jpg',
            'size' => 1024,
            'source' => 'upload',
        ]);

    // processFile pipeline mocks
    $this->fileStorage->shouldReceive('generateFileGuid')->andReturn('guid');
    $this->fileMetadata->shouldReceive('generateJobName')->andReturn('Job');
    $this->fileValidation->shouldReceive('validateFileData')->andReturn(['valid' => true]);
    $this->fileDuplication->shouldReceive('checkDuplication')
        ->andReturn(['isDuplicate' => false, 'hash' => 'h', 'existingFile' => null]);
    $this->fileStorage->shouldReceive('storeWorkingContent')->andReturn('/tmp/guid.jpg');
    $this->fileMetadata->shouldReceive('createFileRecordFromData')->andReturn($file);
    $this->fileStorage->shouldReceive('storeToS3')->andReturn('s3://path');
    $this->fileMetadata->shouldReceive('updateFileWithS3Path');
    $this->fileStorage->shouldReceive('deleteWorkingFile')->andReturn(true);
    $this->fileMetadata->shouldReceive('prepareFileMetadata')->andReturn([]);
    $this->jobChainDispatcher->shouldReceive('dispatch');

    $result = $this->service->processUpload($uploadedFile, 'receipt', $this->user->id);

    expect($result['success'])->toBeTrue();
});

it('throws when uploaded file validation fails', function () {
    $uploadedFile = UploadedFile::fake()->create('bad.exe', 100);

    $this->fileValidation->shouldReceive('validateUploadedFile')
        ->once()
        ->andReturn(['valid' => false, 'errors' => ['Invalid file type']]);

    $this->service->processUpload($uploadedFile, 'receipt', $this->user->id);
})->throws(Exception::class, 'File validation failed: Invalid file type');

// --- processPulseDavFile ---

it('processes a PulseDav file and deletes from incoming bucket on success', function () {
    $file = File::factory()->create(['user_id' => $this->user->id]);

    $this->fileStorage->shouldReceive('existsInS3')
        ->with('pulsedav', 'inbox/receipt.pdf')
        ->andReturn(true);

    $this->fileStorage->shouldReceive('getFromS3')
        ->with('pulsedav', 'inbox/receipt.pdf')
        ->andReturn('pdf-content');

    $this->fileStorage->shouldReceive('getSizeFromS3')
        ->with('pulsedav', 'inbox/receipt.pdf')
        ->andReturn(4096);

    $this->fileMetadata->shouldReceive('extractFileDataFromPulseDav')
        ->once()
        ->andReturn([
            'content' => 'pdf-content',
            'fileName' => 'receipt.pdf',
            'extension' => 'pdf',
            'size' => 4096,
            'source' => 'pulsedav',
        ]);

    // processFile pipeline mocks
    $this->fileStorage->shouldReceive('generateFileGuid')->andReturn('guid');
    $this->fileMetadata->shouldReceive('generateJobName')->andReturn('Job');
    $this->fileValidation->shouldReceive('validateFileData')->andReturn(['valid' => true]);
    $this->fileDuplication->shouldReceive('checkDuplication')
        ->andReturn(['isDuplicate' => false, 'hash' => 'h', 'existingFile' => null]);
    $this->fileStorage->shouldReceive('storeWorkingContent')->andReturn('/tmp/guid.pdf');
    $this->fileMetadata->shouldReceive('createFileRecordFromData')->andReturn($file);
    $this->fileStorage->shouldReceive('storeToS3')->andReturn('s3://path');
    $this->fileMetadata->shouldReceive('updateFileWithS3Path');
    $this->fileStorage->shouldReceive('deleteWorkingFile')->andReturn(true);
    $this->fileMetadata->shouldReceive('prepareFileMetadata')->andReturn([]);
    $this->jobChainDispatcher->shouldReceive('dispatch');

    $this->fileStorage->shouldReceive('deleteFromS3')
        ->once()
        ->with('pulsedav', 'inbox/receipt.pdf');

    $result = $this->service->processPulseDavFile('inbox/receipt.pdf', 'receipt', $this->user->id);

    expect($result['success'])->toBeTrue();
});

it('throws when PulseDav file does not exist in bucket', function () {
    $this->fileStorage->shouldReceive('existsInS3')
        ->with('pulsedav', 'missing/file.pdf')
        ->andReturn(false);

    $this->service->processPulseDavFile('missing/file.pdf', 'receipt', $this->user->id);
})->throws(Exception::class, 'File not found in PulseDav bucket');

// --- isSupported / getMaxFileSize delegates ---

it('delegates isSupported to validation contract', function () {
    $this->fileValidation->shouldReceive('isSupported')
        ->with('pdf', 'receipt')
        ->once()
        ->andReturn(true);

    expect($this->service->isSupported('pdf', 'receipt'))->toBeTrue();
});

it('delegates getMaxFileSize to validation contract', function () {
    $this->fileValidation->shouldReceive('getMaxFileSize')
        ->with('document')
        ->once()
        ->andReturn(10485760);

    expect($this->service->getMaxFileSize('document'))->toBe(10485760);
});
