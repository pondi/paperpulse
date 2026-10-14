<?php

use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\File;
use App\Models\PublicCollectionLink;
use App\Models\User;
use App\Services\FolderTreeService;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->withoutVite());

test('folder browsing is bounded and tree changes preserve explicit clearing', function () {
    $owner = User::factory()->create();
    $tree = app(FolderTreeService::class);
    $root = $tree->ensureFolder($owner->id, 'Root');
    $child = $tree->ensureFolder($owner->id, 'Child', $root->id);
    $child->update(['description' => 'Clear me']);
    $this->actingAs($owner)->getJson('/api/v1/collections?parent_id='.$root->id)->assertOk()->assertJsonCount(1, 'data');
    $this->patch('/collections/'.$child->id, ['parent_id' => null, 'description' => null])->assertSessionHasNoErrors();
    expect($child->fresh()->parent_id)->toBeNull()->and($child->fresh()->description)->toBeNull();
    $this->getJson('/api/v1/collections?per_page=101')->assertUnprocessable();
    $this->patch('/collections/'.$root->id, ['parent_id' => $root->id])->assertSessionHasErrors('parent_id');
});

test('tree previews count recursive files once and safe deletion retains document bytes', function () {
    $owner = User::factory()->create();
    $tree = app(FolderTreeService::class);
    $root = $tree->ensureFolder($owner->id, 'Root');
    $child = $tree->ensureFolder($owner->id, 'Child', $root->id);
    $file = File::factory()->create(['user_id' => $owner->id, 's3_original_path' => 'unchanged.pdf']);
    $root->files()->attach($file->id);
    $tree->place($file, $child);
    $this->actingAs($owner)->getJson('/collections/'.$root->id.'/tree-preview')->assertOk()
        ->assertJson(['folders' => 2, 'files' => 1, 'can_delete' => false]);
    $this->delete('/collections/'.$root->id)->assertSessionHasErrors('collection');
    $this->post('/collections/'.$root->id.'/archive')->assertRedirect();
    expect($child->fresh()->is_archived)->toBeTrue();
    $this->post('/collections/'.$root->id.'/unarchive')->assertRedirect();
    expect($child->fresh()->is_archived)->toBeFalse();
    $this->delete('/collections/'.$child->id)->assertRedirect();
    expect($file->fresh()->primary_folder_id)->toBeNull()->and($file->fresh()->s3_original_path)->toBe('unchanged.pdf');
    expect($file->fresh()->trashed())->toBeFalse();
});

test('shared and public folders exclude unshared descendants before and after moving', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $tree = app(FolderTreeService::class);
    $root = $tree->ensureFolder($owner->id, 'Shared root');
    $child = $tree->ensureFolder($owner->id, 'Private child');
    $file = File::factory()->create(['user_id' => $owner->id]);
    $tree->place($file, $child);
    CollectionShare::create(['collection_id' => $root->id, 'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $recipient->id, 'permission' => 'edit', 'shared_at' => now()]);
    $tree->update($child, ['parent_id' => $root->id]);
    $this->actingAs($recipient)->get('/collections/'.$root->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('children.data', 0)->has('collection.files', 0));
    $this->patch('/collections/'.$root->id, ['parent_id' => null])->assertForbidden();
    $this->getJson('/collections/'.$root->id.'/tree-preview')->assertForbidden();
    expect($recipient->can('view', $file))->toBeFalse();
    $link = PublicCollectionLink::factory()->create(['collection_id' => $root->id, 'created_by_user_id' => $owner->id]);
    $this->get(route('shared.collections.show', $link->token))->assertInertia(fn (AssertableInertia $page) => $page->has('files', 0));
});

test('lazy folder pages bound large trees and retain separate file pagination and complete counts', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $tree = app(FolderTreeService::class);
    $root = $tree->ensureFolder($owner->id, 'Root');
    Collection::factory()->count(51)->create(['user_id' => $owner->id, 'parent_id' => $root->id]);
    $foreign = Collection::factory()->create(['user_id' => $other->id]);
    $files = File::factory()->count(51)->create(['user_id' => $owner->id]);
    $root->files()->attach($files->modelKeys());
    $this->actingAs($owner)->getJson('/collections/folders?parent_id='.$root->id)
        ->assertOk()->assertJsonCount(50, 'folders.data')->assertJsonPath('folders.total', 51)->assertJsonPath('path.0.label', 'Root');
    $this->getJson('/collections/folders?parent_id='.$foreign->id)->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    $this->get('/collections/'.$root->id.'?children_page=2&files_page=2')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('children.data', 1)->has('collection.files', 1)->where('children.current_page', 2)
        ->where('stats.total_files', 51)->where('treePreview.files', 51));
});

test('folder explorer scopes search and counts while retaining sorting and archive filters across pages', function (): void {
    $owner = User::factory()->create();
    $parent = Collection::factory()->for($owner, 'user')->create();
    Collection::factory()->for($owner, 'user')->create(['name' => 'Folder outside']);
    Collection::factory()->create(['name' => 'Folder private']);
    foreach (range(1, 21) as $number) {
        Collection::factory()->for($owner, 'user')->create(['parent_id' => $parent->id, 'name' => sprintf('Folder %02d', $number)]);
    }
    $archived = Collection::factory()->for($owner, 'user')->archived()->create(['parent_id' => $parent->id, 'name' => 'Folder 22']);
    Collection::factory()->for($owner, 'user')->create(['parent_id' => $archived->id]);
    $archived->files()->attach(File::factory()->for($owner, 'user')->create()->id);

    $this->actingAs($owner)->get(route('collections.index', ['parent_id' => $parent->id, 'search' => 'Folder', 'sort' => '-name']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('collections.total', 21)->where('collections.data.0.name', 'Folder 21'));
    $this->get(route('collections.index', ['parent_id' => $parent->id, 'search' => 'Folder', 'archived' => 1, 'sort' => 'files']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('collections.total', 22)
        ->where('collections.data.0.id', $archived->id)->where('collections.data.0.files_count', 1)->where('collections.data.0.children_count', 1)
        ->where('collections.next_page_url', fn ($url) => str_contains($url, 'parent_id='.$parent->id) && str_contains($url, 'search=Folder') && str_contains($url, 'sort=files') && str_contains($url, 'archived=1')));
    $this->get(route('collections.index', ['parent_id' => $parent->id, 'search' => 'Folder', 'archived' => 1, 'sort' => '-name', 'page' => 2]))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('collections.data', 2)->where('collections.data.0.name', 'Folder 02'));
    $this->get(route('collections.index', ['parent_id' => $parent->id, 'search' => 'missing']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('collections.total', 0));
});
