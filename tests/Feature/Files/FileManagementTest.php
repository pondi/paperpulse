<?php

use App\Models\File;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Files\StoragePathBuilder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
});

afterEach(function () {
    Mockery::close();
});

it('lists failed files first', function () {
    $user = User::factory()->create();

    File::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
        'uploaded_at' => now()->subMinute(),
    ]);

    File::factory()->create([
        'user_id' => $user->id,
        'status' => 'failed',
        'uploaded_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('files.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Files/Index')
            ->has('files.data', 2)
            ->where('files.data.0.status', 'failed')
        );
});

it('can restart a failed file', function () {
    $user = User::factory()->create();
    $guid = (string) Str::uuid();
    $path = StoragePathBuilder::storagePath($user->id, $guid, 'receipt', 'original', 'pdf');

    Storage::disk('paperpulse')->put($path, 'test');

    $file = File::factory()->create([
        'user_id' => $user->id,
        'guid' => $guid,
        'file_type' => 'receipt',
        'processing_type' => 'receipt',
        'fileExtension' => 'pdf',
        'status' => 'failed',
        's3_original_path' => $path,
    ]);

    $dispatcher = Mockery::mock(FileJobChainDispatcher::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->withArgs(fn (string $jobId, string $fileType) => Str::isUuid($jobId) && $fileType === 'receipt');
    app()->instance(FileJobChainDispatcher::class, $dispatcher);

    $this->actingAs($user)
        ->post(route('files.reprocess', $file))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($file->refresh()->status)->toBe('pending');
    expect(JobHistory::where('file_id', $file->id)->count())->toBe(1);
});

it('can change type, move storage, and restart a failed file', function () {
    $user = User::factory()->create();
    $guid = (string) Str::uuid();
    $oldPath = StoragePathBuilder::storagePath($user->id, $guid, 'receipt', 'original', 'pdf');
    $newPath = StoragePathBuilder::storagePath($user->id, $guid, 'document', 'original', 'pdf');

    Storage::disk('paperpulse')->put($oldPath, 'test');

    $file = File::factory()->create([
        'user_id' => $user->id,
        'guid' => $guid,
        'file_type' => 'receipt',
        'processing_type' => 'receipt',
        'fileExtension' => 'pdf',
        'status' => 'failed',
        's3_original_path' => $oldPath,
    ]);

    $dispatcher = Mockery::mock(FileJobChainDispatcher::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->withArgs(fn (string $jobId, string $fileType) => Str::isUuid($jobId) && $fileType === 'document');
    app()->instance(FileJobChainDispatcher::class, $dispatcher);

    $this->actingAs($user)
        ->patch(route('files.change-type', $file), [
            'file_type' => 'document',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $file->refresh();

    expect($file->file_type)->toBe('document');
    expect($file->s3_original_path)->toBe($newPath);
    expect($file->status)->toBe('pending');

    Storage::disk('paperpulse')->assertMissing($oldPath);
    Storage::disk('paperpulse')->assertExists($newPath);
});
