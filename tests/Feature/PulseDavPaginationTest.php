<?php

use App\Models\PulseDavFile;
use App\Models\User;
use App\Services\PulseDav\PulseDavFolderService;
use App\Services\PulseDav\PulseDavSyncService;
use App\Services\PulseDavService;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['services.pulsedav.s3_incoming_prefix' => 'scans/incoming/', 'filesystems.disks.pulsedav.bucket' => 'scanner-test']);
    Notification::fake();
    $this->user = User::factory()->create();
    $this->prefix = 'scans/incoming/'.$this->user->id.'/';
    $this->s3 = Mockery::mock(S3Client::class);
    Storage::shouldReceive('disk')->with('pulsedav')->andReturn(
        Mockery::mock()->shouldReceive('getClient')->andReturn($this->s3)->getMock()
    );
});

it('syncs every page and resumes safely after a later page fails', function (string $serviceClass, bool $withFolders) {
    $first = new Result([
        'Contents' => [['Key' => $this->prefix.'A/first.pdf', 'Size' => 5, 'LastModified' => now()]],
        'IsTruncated' => true,
        'NextContinuationToken' => 'page-two',
    ]);
    $parameters = ['Bucket' => 'scanner-test', 'Prefix' => $this->prefix];
    $this->s3->shouldReceive('listObjectsV2')->twice()->with($parameters)->andReturn($first);
    $this->s3->shouldReceive('listObjectsV2')->once()->with($parameters + ['ContinuationToken' => 'page-two'])
        ->andThrow(new RuntimeException('Listing unavailable'));
    $service = new $serviceClass;
    $method = $withFolders ? 'syncS3FilesWithFolders' : 'syncS3Files';
    expect(fn () => $service->$method($this->user))->toThrow(RuntimeException::class, 'Listing unavailable');
    $this->assertDatabaseHas('pulsedav_files', ['s3_path' => $this->prefix.'A/first.pdf', 'deleted_at' => null]);

    $this->s3->shouldReceive('listObjectsV2')->once()->with($parameters + ['ContinuationToken' => 'page-two'])
        ->andReturn(new Result(['Contents' => [
            ['Key' => $this->prefix.'A/second.pdf', 'Size' => 7, 'LastModified' => now()],
            ['Key' => $this->prefix.'B/', 'Size' => 0, 'LastModified' => now()],
        ]]));
    expect($service->$method($this->user))->toBe($withFolders ? 2 : 1);
    expect(PulseDavFile::where('is_folder', false)->count())->toBe(2);
    expect(PulseDavFile::where('is_folder', true)->count())->toBe($withFolders ? 2 : 0);
})->with([
    [PulseDavSyncService::class, false], [PulseDavSyncService::class, true],
    [PulseDavService::class, false], [PulseDavService::class, true],
]);

it('browses files and folders across pages', function (string $serviceClass) {
    $parameters = ['Bucket' => 'scanner-test', 'Prefix' => $this->prefix, 'Delimiter' => '/'];
    $this->s3->shouldReceive('listObjectsV2')->once()->with($parameters)->andReturn(new Result([
        'CommonPrefixes' => [['Prefix' => $this->prefix.'A/']],
        'IsTruncated' => true, 'NextContinuationToken' => 'next',
    ]));
    $this->s3->shouldReceive('listObjectsV2')->once()->with($parameters + ['ContinuationToken' => 'next'])
        ->andReturn(new Result(['Contents' => [['Key' => $this->prefix.'last.pdf', 'Size' => 1, 'LastModified' => now()]]]));

    expect(array_column((new $serviceClass)->getFolderContents($this->user), 'name'))->toBe(['A', 'last.pdf']);
})->with([PulseDavFolderService::class, PulseDavService::class]);

it('distinguishes empty folders from later page failures', function () {
    $parameters = ['Bucket' => 'scanner-test', 'Prefix' => $this->prefix, 'Delimiter' => '/'];
    $this->s3->shouldReceive('listObjectsV2')->once()->with($parameters)->andReturn(new Result([]));
    $service = new PulseDavFolderService;
    expect($service->getFolderContents($this->user))->toBe([]);
    $this->s3->shouldReceive('listObjectsV2')->once()->with($parameters)->andReturn(new Result([
        'IsTruncated' => true, 'NextContinuationToken' => 'next',
    ]));
    $this->s3->shouldReceive('listObjectsV2')->once()->with($parameters + ['ContinuationToken' => 'next'])
        ->andThrow(new RuntimeException('Listing unavailable'));
    expect(fn () => $service->getFolderContents($this->user))->toThrow(RuntimeException::class);
});

it('rejects foreign or malformed object paths before syncing them', function (string $path) {
    $this->s3->shouldReceive('listObjectsV2')->once()->andReturn(new Result([
        'Contents' => [['Key' => str_replace('{prefix}', $this->prefix, $path), 'Size' => 1, 'LastModified' => now()]],
    ]));
    expect(fn () => (new PulseDavSyncService)->syncS3Files($this->user))->toThrow(Exception::class);
    $this->assertDatabaseCount('pulsedav_files', 0);
})->with(['scans/incoming/999999/foreign.pdf', '{prefix}../foreign.pdf']);

it('rejects traversal when browsing a folder before listing storage', function () {
    $this->s3->shouldNotReceive('listObjectsV2');
    expect(fn () => (new PulseDavFolderService)->getFolderContents($this->user, '../999999'))
        ->toThrow(ValidationException::class);
});

it('lists every object and deduplicates folders shared by pages', function (string $serviceClass, bool $withFolders) {
    $parameters = ['Bucket' => 'scanner-test', 'Prefix' => $this->prefix];
    $this->s3->shouldReceive('listObjectsV2')->once()->with($parameters)->andReturn(new Result([
        'Contents' => [['Key' => $this->prefix.'A/first.pdf', 'Size' => 1, 'LastModified' => now()]],
        'IsTruncated' => true, 'NextContinuationToken' => 'next',
    ]));
    $this->s3->shouldReceive('listObjectsV2')->once()->with($parameters + ['ContinuationToken' => 'next'])
        ->andReturn(new Result(['Contents' => [['Key' => $this->prefix.'A/last.pdf', 'Size' => 2, 'LastModified' => now()]]]));
    $method = $withFolders ? 'listUserFilesWithFolders' : 'listUserFiles';
    $paths = array_column((new $serviceClass)->$method($this->user), 's3_path');
    expect($paths)->toBe($withFolders
        ? [$this->prefix.'A/', $this->prefix.'A/first.pdf', $this->prefix.'A/last.pdf']
        : [$this->prefix.'A/first.pdf', $this->prefix.'A/last.pdf']);
})->with([
    [PulseDavSyncService::class, false], [PulseDavSyncService::class, true],
    [PulseDavService::class, false], [PulseDavService::class, true],
]);

it('renders direct scanner folder navigation with an empty import list', function (): void {
    $this->withoutVite()->actingAs($this->user)->get(route('pulsedav.folders'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('PulseDav/Index')->where('initialView', 'folder')->has('files.data', 0));
});

it('returns an empty scanner hierarchy for an empty bucket', function (): void {
    $this->s3->shouldReceive('listObjectsV2')->once()->andReturn(new Result([]));
    $this->actingAs($this->user)->getJson(route('pulsedav.folders'))->assertOk()->assertExactJson(['hierarchy' => [], 'total_items' => 0]);
});

it('reports scanner listing failures separately from an empty hierarchy', function (): void {
    $this->s3->shouldReceive('listObjectsV2')->once()->andThrow(new RuntimeException('Provider credentials secret'));
    $this->actingAs($this->user)->getJson(route('pulsedav.folders'))->assertServiceUnavailable()
        ->assertExactJson(['error' => 'Scanner folders could not be loaded. Please try again.']);
});
