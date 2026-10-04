<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DuplicateFlag;
use App\Models\File;
use App\Models\FileCleanupManifest;
use App\Models\User;
use App\Services\Files\FileCleanupService;
use App\Services\Files\FileDeletionService;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Auth ---

it('requires authentication for duplicate index', function () {
    $this->get(route('duplicates.index'))
        ->assertRedirect(route('login'));
});

// --- Index ---

it('lists open duplicate flags for authenticated user', function () {
    $user = User::factory()->create();
    $fileA = File::factory()->create(['user_id' => $user->id]);
    $fileB = File::factory()->create(['user_id' => $user->id]);

    DuplicateFlag::create([
        'user_id' => $user->id,
        'file_id' => $fileA->id,
        'duplicate_file_id' => $fileB->id,
        'reasons' => ['matching hash'],
        'status' => 'open',
    ]);

    $this->actingAs($user)
        ->get(route('duplicates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Duplicates/Index')
            ->has('duplicates', 1)
        );
});

it('does not show resolved duplicate flags', function () {
    $user = User::factory()->create();
    $fileA = File::factory()->create(['user_id' => $user->id]);
    $fileB = File::factory()->create(['user_id' => $user->id]);

    DuplicateFlag::create([
        'user_id' => $user->id,
        'file_id' => $fileA->id,
        'duplicate_file_id' => $fileB->id,
        'reasons' => ['matching hash'],
        'status' => 'resolved',
        'resolved_file_id' => $fileB->id,
        'resolved_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('duplicates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('duplicates', 0));
});

it('returns empty when no duplicate flags exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('duplicates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('duplicates', 0));
});

// --- Resolve ---

it('resolves a duplicate by deleting the chosen file', function () {
    $user = User::factory()->create();
    $fileA = File::factory()->create(['user_id' => $user->id]);
    $fileB = File::factory()->create(['user_id' => $user->id]);

    $flag = DuplicateFlag::create([
        'user_id' => $user->id,
        'file_id' => $fileA->id,
        'duplicate_file_id' => $fileB->id,
        'reasons' => ['matching hash'],
        'status' => 'open',
    ]);

    $document = Document::factory()->create(['user_id' => $user->id, 'file_id' => $fileB->id]);
    $this->mock(StorageService::class)->shouldNotReceive('deleteFile', 'deleteDirectory');

    $this->actingAs($user)
        ->post(route('duplicates.resolve', $flag), [
            'delete_file_id' => $fileB->id,
        ])
        ->assertRedirect();

    expect($flag->fresh()->status)->toBe('resolved');
    $this->assertSoftDeleted('files', ['id' => $fileB->id]);
    $this->assertSoftDeleted('documents', ['id' => $document->id]);
    $manifest = FileCleanupManifest::where('file_id', $fileB->id)->firstOrFail();
    expect($manifest->objects)->not->toBeEmpty();
    $resolvedAt = $flag->fresh()->resolved_at;

    $this->post(route('duplicates.resolve', $flag), ['delete_file_id' => $fileB->id])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(FileCleanupManifest::count())->toBe(1)
        ->and($flag->fresh()->resolved_at->equalTo($resolvedAt))->toBeTrue();
    $this->post(route('duplicates.resolve', $flag), ['delete_file_id' => $fileA->id])
        ->assertSessionHasErrors('delete_file_id');
    expect($fileA->fresh()->trashed())->toBeFalse();
});

it('retries duplicate cleanup without deleting the retained original', function () {
    $user = User::factory()->create();
    $path = 'documents/'.$user->id.'/shared.pdf';
    $retained = File::factory()->create(['user_id' => $user->id, 's3_original_path' => $path]);
    $deleted = File::factory()->create(['user_id' => $user->id, 's3_original_path' => $path]);
    $flag = DuplicateFlag::create([
        'user_id' => $user->id, 'file_id' => $retained->id, 'duplicate_file_id' => $deleted->id,
        'reasons' => ['matching hash'], 'status' => 'open',
    ]);
    $this->actingAs($user)->post(route('duplicates.resolve', $flag), ['delete_file_id' => $deleted->id])
        ->assertRedirect()->assertSessionHasNoErrors();
    $manifest = FileCleanupManifest::where('file_id', $deleted->id)->firstOrFail();
    $manifest->update(['available_at' => now()->subSecond()]);
    $uniquePath = collect($manifest->objects)->first(fn (array $object): bool => $object['path'] !== $path)['path'];
    $storage = Mockery::mock(StorageService::class);
    $storage->shouldNotReceive('deleteFile')->with($path);
    $storage->shouldReceive('deleteFile')->with($uniquePath)->once()->andReturn(false);
    expect((new FileCleanupService($storage))->process($manifest)['failed'])->toBe(1)
        ->and($manifest->fresh()->completed_at)->toBeNull()
        ->and($manifest->fresh()->objects[$path]['done'])->toBeTrue()
        ->and($manifest->fresh()->objects[$uniquePath]['done'])->toBeFalse();

    $storage = Mockery::mock(StorageService::class);
    $storage->shouldNotReceive('deleteFile')->with($path);
    $storage->shouldReceive('deleteFile')->with($uniquePath)->once()->andReturn(true);
    expect((new FileCleanupService($storage))->process($manifest)['failed'])->toBe(0)
        ->and($manifest->fresh()->completed_at)->not->toBeNull()
        ->and($manifest->fresh()->last_error)->toBeNull()
        ->and($manifest->fresh()->objects[$uniquePath]['done'])->toBeTrue()
        ->and($retained->fresh()->trashed())->toBeFalse();
});

it('keeps a duplicate unresolved when its cleanup cannot be persisted', function () {
    $user = User::factory()->create();
    $retained = File::factory()->create(['user_id' => $user->id]);
    $deleted = File::factory()->create(['user_id' => $user->id]);
    $flag = DuplicateFlag::create([
        'user_id' => $user->id, 'file_id' => $retained->id, 'duplicate_file_id' => $deleted->id,
        'reasons' => ['matching hash'], 'status' => 'open',
    ]);
    $this->mock(FileDeletionService::class)->shouldReceive('deleteFile')->once()
        ->andThrow(new RuntimeException('Cleanup persistence failed'));
    $this->actingAs($user)->post(route('duplicates.resolve', $flag), ['delete_file_id' => $deleted->id])
        ->assertServerError();
    expect($flag->fresh()->status)->toBe('open')
        ->and($deleted->fresh()->trashed())->toBeFalse()
        ->and(FileCleanupManifest::count())->toBe(0);
});

it('rejects resolve with invalid file id', function () {
    $user = User::factory()->create();
    $fileA = File::factory()->create(['user_id' => $user->id]);
    $fileB = File::factory()->create(['user_id' => $user->id]);
    $unrelated = File::factory()->create(['user_id' => $user->id]);

    $flag = DuplicateFlag::create([
        'user_id' => $user->id,
        'file_id' => $fileA->id,
        'duplicate_file_id' => $fileB->id,
        'reasons' => ['matching hash'],
        'status' => 'open',
    ]);

    $this->actingAs($user)
        ->post(route('duplicates.resolve', $flag), [
            'delete_file_id' => $unrelated->id,
        ])
        ->assertSessionHasErrors('delete_file_id');
});

it('prevents resolving another users duplicate flag', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $fileA = File::factory()->create(['user_id' => $owner->id]);
    $fileB = File::factory()->create(['user_id' => $owner->id]);

    $flag = DuplicateFlag::create([
        'user_id' => $owner->id,
        'file_id' => $fileA->id,
        'duplicate_file_id' => $fileB->id,
        'reasons' => ['matching hash'],
        'status' => 'open',
    ]);

    $this->actingAs($intruder)
        ->post(route('duplicates.resolve', $flag), [
            'delete_file_id' => $fileB->id,
        ])
        ->assertForbidden();
});

// --- Ignore ---

it('ignores a duplicate flag by deleting it', function () {
    $user = User::factory()->create();
    $fileA = File::factory()->create(['user_id' => $user->id]);
    $fileB = File::factory()->create(['user_id' => $user->id]);

    $flag = DuplicateFlag::create([
        'user_id' => $user->id,
        'file_id' => $fileA->id,
        'duplicate_file_id' => $fileB->id,
        'reasons' => ['matching hash'],
        'status' => 'open',
    ]);

    $this->actingAs($user)
        ->post(route('duplicates.ignore', $flag))
        ->assertRedirect();

    $this->assertDatabaseMissing('duplicate_flags', ['id' => $flag->id]);
});

it('prevents ignoring another users duplicate flag', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $fileA = File::factory()->create(['user_id' => $owner->id]);
    $fileB = File::factory()->create(['user_id' => $owner->id]);

    $flag = DuplicateFlag::create([
        'user_id' => $owner->id,
        'file_id' => $fileA->id,
        'duplicate_file_id' => $fileB->id,
        'reasons' => ['matching hash'],
        'status' => 'open',
    ]);

    $this->actingAs($intruder)
        ->post(route('duplicates.ignore', $flag))
        ->assertForbidden();
});
