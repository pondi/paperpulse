<?php

use App\Models\File;
use App\Models\User;
use App\Services\FolderTreeService;
use App\Services\OrganizationPlanner;
use App\Services\OrganizationRunService;

beforeEach(fn () => $this->withoutVite());

it('shows an empty owner review and requires authentication', function () {
    $this->get(route('collections.organization.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('collections.organization.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Collections/Recommendations')->where('run', null)->where('pending_count', 0)->where('can_start', false));
});

it('shows owned paths counts preview links pending state and late inputs on UI and API', function () {
    $user = User::factory()->create();
    $folder = app(FolderTreeService::class)->ensureFolder($user->id, 'Building');
    $file = File::factory()->create(['user_id' => $user->id, 'fileExtension' => 'pdf', 'fileName' => 'Lease.pdf']);
    app(FolderTreeService::class)->place($file, $folder, 'system');
    $run = app(OrganizationRunService::class)->start($user->id);
    $run->recommendations()->create(['user_id' => $user->id, 'operation' => ['type' => 'rename', 'folder_id' => $folder->id,
        'parent_id' => null, 'file_ids' => [$file->id], 'name' => 'Home'],
        'before_state' => ['files' => [], 'folders' => [$folder->id => app(OrganizationPlanner::class)->folderState($folder)]],
        'signature' => str_repeat('a', 64), 'confidence' => .95, 'reason' => 'Use your preferred name']);
    app(OrganizationRunService::class)->finish($run);
    $file->update(['organization_summary' => ['title' => 'Later input']]);
    $this->actingAs($user)->get(route('collections.organization.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Collections/Recommendations')->where('pending_count', 1)->where('changes_waiting', true)->where('can_start', false)
        ->where('recommendations.data.0.current_paths', ['Building'])->where('recommendations.data.0.proposed_path', 'Home')
        ->where('recommendations.data.0.affected_count', 1)->where('recommendations.data.0.preview_items.0.id', $file->id));
    $this->getJson(route('api.organization.index'))->assertOk()->assertJsonPath('data.pending_count', 1);
    $other = User::factory()->create();
    $this->actingAs($other)->getJson(route('api.organization.index'))->assertOk()->assertJsonPath('data.run', null);
});

it('validates selected recommendation ownership and exposes failed run recovery', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($user->id);
    $runs->fail($runs->begin($user->id, $run->id));
    $this->actingAs($user)->get(route('collections.organization.index'))->assertInertia(fn ($page) => $page
        ->where('run.status', 'failed')->where('can_start', false)
        ->has('run.created_at')->has('run.started_at')->has('run.updated_at'));
    $this->postJson(route('api.organization.decide'), ['recommendation_ids' => [9999], 'decision' => 'apply'])->assertUnprocessable();
    $this->post(route('collections.organization.retry', $run->id))->assertRedirect();
    expect($run->fresh()->status)->toBe('queued');
    $this->actingAs(User::factory()->create())->postJson(route('api.organization.retry', $run->id))->assertNotFound();
});
