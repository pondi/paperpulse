<?php

use App\Contracts\Services\FileStorageContract;
use App\Jobs\PulseDav\ProcessPulseDavFile;
use App\Models\PulseDavFile;
use App\Models\User;
use App\Services\FileProcessingService;
use App\Services\PulseDav\Import\FileRecordCreator;
use App\Services\PulseDav\Import\ImportValidator;
use App\Services\PulseDav\Import\SmartSyncService;
use App\Services\PulseDav\SelectionImportService;
use App\Services\PulseDavService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['services.pulsedav.s3_incoming_prefix' => 'scans/incoming/']);
    Bus::fake();
});

it('rejects foreign and malformed selections before any storage access', function (string $suffix) {
    $user = User::factory()->create();
    $path = str_replace('{user}', (string) $user->id, $suffix);
    Storage::shouldReceive('disk')->never();

    $result = SelectionImportService::importSelected($user, [['s3_path' => $path]]);

    expect($result)->toBe(['batch_id' => null, 'imported' => 0, 'skipped' => 1])
        ->and(SmartSyncService::syncSelectionsIfNeeded($user, [['s3_path' => $path]]))->toBe(0);
    expect(fn () => FileRecordCreator::createFromS3Path($path, $user))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('pulsedav_files', 0);
    Bus::assertNothingDispatched();
})->with([
    'foreign file' => 'scans/incoming/999999/file.pdf',
    'foreign folder' => 'scans/incoming/999999/folder/',
    'prefix collision' => 'scans/incoming/{user}0/file.pdf',
    'old prefix' => 'incoming/{user}/file.pdf',
    'traversal' => 'scans/incoming/{user}/../999999/file.pdf',
    'dot segment' => 'scans/incoming/{user}/./file.pdf',
    'double slash' => 'scans/incoming/{user}//file.pdf',
    'backslash' => 'scans/incoming/{user}/folder\\file.pdf',
    'control byte' => "scans/incoming/{user}/file\0.pdf",
    'absolute path' => '/scans/incoming/{user}/file.pdf',
    'root prefix' => 'scans/incoming/{user}/',
]);

it('rejects missing files and forged folders without creating records', function () {
    $user = User::factory()->create();
    Storage::fake('pulsedav');
    $path = "scans/incoming/{$user->id}/missing.pdf";
    $selections = [['s3_path' => $path], ['s3_path' => "scans/incoming/{$user->id}/missing/"]];

    expect(ImportValidator::validateSelections($selections, $user)['valid'])->toBe([])
        ->and(SmartSyncService::syncSelectionsIfNeeded($user, $selections))->toBe(0);
    expect(fn () => FileRecordCreator::createFromS3Path($path, $user))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('pulsedav_files', 0);
});

it('creates records using the configured prefix for files and virtual folders', function () {
    $user = User::factory()->create();
    Storage::fake('pulsedav');
    $path = "scans/incoming/{$user->id}/invoices/file.pdf";
    Storage::disk('pulsedav')->put($path, 'PDF bytes');

    $file = FileRecordCreator::createFromS3Path($path, $user);
    $folder = FileRecordCreator::createFromS3Path(dirname($path).'/', $user);

    expect($file->s3_path)->toBe($path)
        ->and($file->folder_path)->toBe('invoices')
        ->and($file->size)->toBe(9)
        ->and($folder->is_folder)->toBeTrue()
        ->and($folder->folder_path)->toBe('invoices');
});

it('imports an owned file and rejects foreign selections over HTTP', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Storage::fake('pulsedav');
    $ownedPath = "scans/incoming/{$user->id}/file.pdf";
    $foreignPath = "scans/incoming/{$other->id}/file.pdf";
    Storage::disk('pulsedav')->put($ownedPath, 'owned');
    Storage::disk('pulsedav')->put($foreignPath, 'private');
    $this->mock(PulseDavService::class)->shouldReceive('importSelections')
        ->andReturnUsing(fn (User $owner, array $selections, array $options) => SelectionImportService::importSelected($owner, $selections, $options));

    $this->actingAs($user)->postJson(route('pulsedav.import'), [
        'file_type' => 'receipt',
        'selections' => [
            ['s3_path' => $ownedPath],
            ['s3_path' => $foreignPath],
            ['s3_path' => dirname($foreignPath).'/'],
        ],
    ])->assertOk()->assertJson(['imported' => 1, 'skipped' => 2]);

    $this->assertDatabaseCount('pulsedav_files', 1);
    $this->assertDatabaseHas('pulsedav_files', ['user_id' => $user->id, 's3_path' => $ownedPath]);
    Storage::disk('pulsedav')->assertExists($foreignPath);
    Bus::assertDispatchedTimes(ProcessPulseDavFile::class, 1);
});

it('rejects foreign paths in direct processing before reading or deleting storage', function () {
    $user = User::factory()->create();
    $this->mock(FileStorageContract::class)->shouldNotReceive('existsInS3', 'getFromS3', 'deleteFromS3');
    $service = app(FileProcessingService::class);

    expect(fn () => $service->processPulseDavFile('scans/incoming/999999/file.pdf', 'receipt', $user->id))
        ->toThrow(ValidationException::class);
});

it('rejects a queued record pointing at another users object', function () {
    $user = User::factory()->create();
    $file = PulseDavFile::create([
        'user_id' => $user->id,
        's3_path' => 'scans/incoming/999999/file.pdf',
        'filename' => 'file.pdf',
        'size' => 7,
        'status' => 'pending',
        'uploaded_at' => now(),
    ]);
    $this->mock(FileStorageContract::class)->shouldNotReceive('existsInS3', 'getFromS3', 'deleteFromS3');

    expect(fn () => (new ProcessPulseDavFile($file))->handle())->toThrow(ValidationException::class);
    expect($file->fresh()->status)->toBe('failed');
});
