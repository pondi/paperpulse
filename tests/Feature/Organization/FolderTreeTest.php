<?php

use App\Models\Collection;
use App\Models\File;
use App\Models\User;
use App\Services\FolderTreeService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

test('folder identity reuses roots and children independently for each tenant', function () {
    $users = User::factory()->count(2)->create();
    $tree = app(FolderTreeService::class);
    $first = $tree->ensureFolder($users[0]->id, 'Building');
    $second = $tree->ensureFolder($users[0]->id, ' BUILDING ');
    $foreign = $tree->ensureFolder($users[1]->id, 'Building');
    expect($second->id)->toBe($first->id)->and($foreign->id)->not->toBe($first->id);
    $address = $tree->ensureFolder($users[0]->id, 'Main Street', $first->id);
    expect($tree->ensureFolder($users[0]->id, 'main street', $first->id)->id)->toBe($address->id);
    expect(fn () => Collection::create(['user_id' => $users[0]->id, 'name' => 'building']))->toThrow(QueryException::class);
});

test('foreign parents and tree cycles are rejected', function () {
    $owner = User::factory()->create();
    $foreign = Collection::factory()->create();
    $root = Collection::factory()->create(['user_id' => $owner->id]);
    $child = app(FolderTreeService::class)->ensureFolder($owner->id, 'Child', $root->id);
    expect(fn () => $root->update(['parent_id' => $child->id]))->toThrow(ValidationException::class);
    expect(fn () => app(FolderTreeService::class)->ensureFolder($owner->id, 'Foreign child', $foreign->id))->toThrow(ValidationException::class);
});

test('primary placement retains secondary references without moving original storage', function () {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id, 's3_original_path' => 'unchanged/original.pdf']);
    $tree = app(FolderTreeService::class);
    $first = $tree->ensureFolder($owner->id, 'First');
    $second = $tree->ensureFolder($owner->id, 'Second');
    $reference = $tree->ensureFolder($owner->id, 'Reference');
    $file->collections()->attach($reference->id);
    $tree->place($file, $first);
    $placed = $tree->place($file, $second);
    expect($placed->primary_folder_id)->toBe($second->id);
    expect($placed->collections->modelKeys())->toEqualCanonicalizing([$reference->id, $second->id]);
    expect($placed->s3_original_path)->toBe('unchanged/original.pdf');
    expect(File::count())->toBe(1);
    expect(fn () => $tree->place($file, Collection::factory()->create()))->toThrow(ValidationException::class);
});
