<?php

use App\Jobs\PulseDav\ProcessPulseDavFile;
use App\Models\PulseDavFile;
use App\Models\User;
use App\Services\PulseDav\PulseDavFolderService;
use App\Services\PulseDav\SelectionImportService;
use App\Services\PulseDavService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['services.pulsedav.s3_incoming_prefix' => 'scans/incoming/']);
    Bus::fake();
    Storage::fake('pulsedav');
    $this->user = User::factory()->create();
    $this->service = new class('scans/incoming/') extends PulseDavFolderService
    {
        public function __construct(string $prefix)
        {
            $this->incomingPrefix = $prefix;
        }
    };
});

it('keeps root folder predicates within the owner and file filters', function (?string $rootPath): void {
    $other = User::factory()->create();
    $ownedIds = [];
    foreach ([$this->user, $other] as $owner) {
        foreach ([null, '', 'nested'] as $index => $parentFolder) {
            foreach ([false, true] as $isFolder) {
                $file = PulseDavFile::create([
                    'user_id' => $owner->id,
                    's3_path' => "scans/incoming/{$owner->id}/{$index}-".(int) $isFolder,
                    'filename' => 'entry', 'parent_folder' => $parentFolder,
                    'is_folder' => $isFolder, 'status' => $isFolder ? 'folder' : 'pending',
                    'size' => 0, 'uploaded_at' => now(),
                ]);
                if ($owner->is($this->user) && ! $isFolder && $parentFolder !== 'nested') {
                    $ownedIds[] = $file->id;
                }
            }
        }
    }

    expect(PulseDavFile::forUser($this->user->id)->filesOnly()->inFolder($rootPath)->pluck('id')->all())
        ->toBe($ownedIds);
})->with([null, '', '/']);

it('timestamps virtual folders and preserves them when reused', function (): void {
    $this->freezeSecond();
    $folder = $this->service->createVirtualFolder($this->user, 'virtual/child');
    expect($folder->fresh()->uploaded_at->equalTo(now()))->toBeTrue()
        ->and($folder->parent_folder)->toBe('virtual');

    $this->travel(1)->day();
    expect($this->service->createVirtualFolder($this->user, 'virtual/child')->id)->toBe($folder->id)
        ->and($folder->fresh()->uploaded_at->equalTo(now()->subDay()))->toBeTrue();
});

it('timestamps a missing folder when tags are saved through either service', function (bool $useFacade): void {
    $this->freezeSecond();
    $service = $useFacade ? new class extends PulseDavService
    {
        public function __construct()
        {
            $this->incomingPrefix = 'scans/incoming/';
        }
    } : $this->service;

    $folder = $service->updateFolderTags($this->user, 'tagged/child', []);
    expect($folder->fresh()->uploaded_at->equalTo(now()))->toBeTrue()
        ->and($folder->s3_path)->toBe("scans/incoming/{$this->user->id}/tagged/child/")
        ->and($folder->folder_tag_ids)->toBe([]);
    expect($service->updateFolderTags($this->user, 'tagged/child', [1])->id)->toBe($folder->id)
        ->and($folder->fresh()->folder_tag_ids)->toBe([1]);
})->with([false, true]);

it('counts imports and deletes only the exact owned folder and descendants', function (string $folderPath, string $siblingPath) {
    $other = User::factory()->create();
    $folder = PulseDavFile::create([
        'user_id' => $this->user->id, 's3_path' => "scans/incoming/{$this->user->id}/{$folderPath}/",
        'filename' => basename($folderPath), 'folder_path' => $folderPath,
        'is_folder' => true, 'status' => 'folder', 'size' => 0, 'uploaded_at' => now(),
    ]);
    $files = collect();
    foreach ([[$this->user, $folderPath], [$this->user, $folderPath.'/child'], [$this->user, $siblingPath], [$other, $folderPath]] as [$owner, $path]) {
        $s3Path = "scans/incoming/{$owner->id}/{$path}/file.pdf";
        Storage::disk('pulsedav')->put($s3Path, 'PDF');
        $files->push(PulseDavFile::create([
            'user_id' => $owner->id, 's3_path' => $s3Path, 'filename' => 'file.pdf',
            'folder_path' => $path, 'uploaded_at' => now(), 'size' => 3, 'status' => 'pending', 'is_folder' => false,
        ]));
    }

    expect($this->service->getFolderStats($this->user, '/'.$folderPath.'/')['total_files'])->toBe(2)
        ->and($this->service->getUserFolders($this->user)[0]['file_count'])->toBe(2);
    $result = SelectionImportService::importSelected($this->user, [['s3_path' => $folder->s3_path]]);
    expect($result['imported'])->toBe(1);
    Bus::assertDispatchedTimes(ProcessPulseDavFile::class, 2);
    expect($files[2]->fresh()->status)->toBe('pending')->and($files[3]->fresh()->status)->toBe('pending');

    expect($this->service->deleteFolder($this->user, '/'.$folderPath.'/'))->toBe(3);
    foreach ($files->take(2) as $file) {
        $this->assertSoftDeleted($file);
        Storage::disk('pulsedav')->assertMissing($file->s3_path);
    }
    foreach ($files->slice(2) as $file) {
        expect($file->fresh()->deleted_at)->toBeNull();
        Storage::disk('pulsedav')->assertExists($file->s3_path);
    }
})->with([
    ['A', 'A2'], ['A%_', 'Axx'], ['A!_', 'A!x'], ['nested/A', 'nested/A2'],
]);

it('rejects malformed folder operations before changing any records', function (string $folderPath) {
    expect(fn () => $this->service->createVirtualFolder($this->user, $folderPath))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->getFolderStats($this->user, $folderPath))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->deleteFolder($this->user, $folderPath))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('pulsedav_files', 0);
})->with(['../other', 'A/../other', 'A//child', 'A\\child', "A\0child"]);

it('cannot delete a foreign object through a forged owned record', function () {
    $foreignPath = 'scans/incoming/999999/A/foreign.pdf';
    Storage::disk('pulsedav')->put($foreignPath, 'private');
    $file = PulseDavFile::create([
        'user_id' => $this->user->id, 's3_path' => $foreignPath, 'filename' => 'foreign.pdf',
        'folder_path' => 'A', 'uploaded_at' => now(), 'size' => 7, 'status' => 'pending',
    ]);

    expect($this->service->deleteFolder($this->user, 'A'))->toBe(0)->and($file->fresh()->deleted_at)->toBeNull();
    Storage::disk('pulsedav')->assertExists($foreignPath);
});
