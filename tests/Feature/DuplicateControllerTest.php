<?php

declare(strict_types=1);

use App\Models\DuplicateFlag;
use App\Models\File;
use App\Models\User;
use App\Services\DocumentService;
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

    // Mock external storage services
    $this->mock(StorageService::class, function ($mock) {
        $mock->shouldReceive('deleteDirectory')->andReturn(true);
        $mock->shouldReceive('deleteFile')->andReturn(true);
    });
    $this->mock(DocumentService::class, function ($mock) {
        $mock->shouldReceive('deleteDocument')->andReturn(true);
    });

    $this->actingAs($user)
        ->post(route('duplicates.resolve', $flag), [
            'delete_file_id' => $fileB->id,
        ])
        ->assertRedirect();

    expect($flag->fresh()->status)->toBe('resolved');
    $this->assertSoftDeleted('files', ['id' => $fileB->id]);
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
