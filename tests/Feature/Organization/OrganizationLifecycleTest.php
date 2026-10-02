<?php

use App\Contracts\Services\TextAnalysisContract;
use App\Jobs\Organization\GenerateOrganizationRecommendations;
use App\Models\File;
use App\Models\OrganizationRun;
use App\Models\User;
use App\Services\AI\Shared\ProcessingUsageBudget;
use App\Services\FolderTreeService;
use App\Services\OrganizationDecisionService;
use App\Services\OrganizationPlanner;
use App\Services\OrganizationRevisionService;
use App\Services\OrganizationRunService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
    $this->providerCalls = 0;
    $provider = Mockery::mock(TextAnalysisContract::class);
    $provider->shouldReceive('getProviderName')->andReturn('fake');
    $provider->shouldReceive('analyze')->andReturnUsing(function (string $prompt): array {
        $this->providerCalls++;
        ProcessingUsageBudget::reserve(strlen($prompt) + 100);
        ProcessingUsageBudget::record(100, 100);
        $input = json_decode(substr($prompt, strpos($prompt, '{')), true, flags: JSON_THROW_ON_ERROR);
        $file = array_values($input['groups'])[0][0];
        $userId = File::withoutGlobalScope('user')->findOrFail($file['id'])->user_id;
        $active = app(OrganizationRunService::class)->start($userId);
        expect($active->status)->toBe('running');
        expect(app(OrganizationRunService::class)->begin($userId, $active->id))->toBeNull();

        return ['operations' => [['type' => 'create', 'folder_id' => null, 'target_id' => null, 'parent_id' => null,
            'file_ids' => [], 'name' => 'Building contracts', 'confidence' => .95, 'reason' => 'Group building contracts']]];
    });
    app()->instance(TextAnalysisContract::class, $provider);
});

function lifecycleFile(User $user): File
{
    return File::factory()->create(['user_id' => $user->id, 'organization_summary' => [
        'version' => 1, 'title' => 'Lease', 'property_address' => 'Same address',
        'employer' => ['name' => 'Same company'], 'role' => 'contracts', 'confidence' => .95,
    ]]);
}

it('isolates identical evidence across users and blocks interleaved workers with exact call counts', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $runs = app(OrganizationRunService::class);
    $planned = [];
    foreach ([$first, $second] as $owner) {
        lifecycleFile($owner);
        $folder = app(FolderTreeService::class)->ensureFolder($owner->id, 'Building', source: 'system');
        $run = $runs->start($owner->id);
        (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class));
        $planned[] = $run;
        (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class));
        expect($runs->start($owner->id)->id)->toBe($run->id);
        expect($run->recommendations()->first()->user_id)->toBe($owner->id);
    }
    expect($this->providerCalls)->toBe(2);
    $foreign = $planned[1]->recommendations()->first();
    $this->actingAs($first)->postJson(route('api.organization.decide'), [
        'recommendation_ids' => [$foreign->id], 'decision' => 'apply',
    ])->assertUnprocessable();
    $this->post(route('collections.organization.undo', $foreign->id))->assertNotFound();
    expect(fn () => (new GenerateOrganizationRecommendations($first->id, $planned[1]->id))->handle($runs, app(OrganizationPlanner::class)))
        ->toThrow(ModelNotFoundException::class);
    $this->getJson(route('api.organization.index'))->assertJsonPath('data.run.id', $planned[0]->id);
});

it('all declined inputs cause no rerun and later evidence invalidates only its suppression', function () {
    $owner = User::factory()->create();
    $file = lifecycleFile($owner);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class));
    $recommendation = $run->recommendations()->first();
    $this->actingAs($owner)->postJson(route('api.organization.decide'), ['recommendation_ids' => [$recommendation->id], 'decision' => 'decline', 'reason' => 'Keep current folders'])->assertOk();
    expect($runs->start($owner->id))->toBeNull()->and($this->providerCalls)->toBe(1);
    $file->update(['organization_summary' => array_replace($file->organization_summary, ['title' => 'Revised lease'])]);
    $next = $runs->start($owner->id);
    (new GenerateOrganizationRecommendations($owner->id, $next->id))->handle($runs, app(OrganizationPlanner::class));
    expect($next->recommendations()->count())->toBe(1)->and($this->providerCalls)->toBe(2);
});

it('late inputs survive pending review and stale apply without a self triggered loop', function () {
    $owner = User::factory()->create();
    $file = lifecycleFile($owner);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class));
    $file->update(['organization_summary' => array_replace($file->organization_summary, ['title' => 'Late lease'])]);
    expect($runs->start($owner->id)->id)->toBe($run->id)->and($this->providerCalls)->toBe(1);
    $recommendation = $run->recommendations()->first();
    app(OrganizationDecisionService::class)->decide($owner->id, [$recommendation->id], 'apply');
    expect($recommendation->fresh()->status)->toBe('conflict');
    app(OrganizationDecisionService::class)->decide($owner->id, [$recommendation->id], 'decline');
    expect(app(OrganizationRevisionService::class)->hasChanges($owner->id))->toBeTrue();
    $next = $runs->start($owner->id);
    (new GenerateOrganizationRecommendations($owner->id, $next->id))->handle($runs, app(OrganizationPlanner::class));
    app(OrganizationDecisionService::class)->decide($owner->id, [$next->recommendations()->first()->id], 'apply');
    expect($runs->start($owner->id))->toBeNull()->and($this->providerCalls)->toBe(2);
});

it('a failed provider retry reuses the run and does not duplicate recommendations', function () {
    $owner = User::factory()->create();
    lifecycleFile($owner);
    $provider = Mockery::mock(TextAnalysisContract::class);
    $provider->shouldReceive('getProviderName')->andReturn('retry-fake');
    $provider->shouldReceive('analyze')->once()->andThrow(new RuntimeException('Temporary provider failure'));
    $provider->shouldReceive('analyze')->once()->andReturn(['operations' => []]);
    app()->instance(TextAnalysisContract::class, $provider);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    $job = new GenerateOrganizationRecommendations($owner->id, $run->id);
    expect(fn () => $job->handle($runs, app(OrganizationPlanner::class)))->toThrow(RuntimeException::class);
    $runs->retry($owner->id, $run->id);
    $job->handle($runs, app(OrganizationPlanner::class));
    expect($run->fresh()->status)->toBe('completed')->and($run->fresh()->attempts)->toBe(2)
        ->and(OrganizationRun::query()->count())->toBe(1)->and($run->recommendations()->count())->toBe(0);
});

it('enforces active run uniqueness at the database boundary', function () {
    $owner = User::factory()->create();
    lifecycleFile($owner);
    $run = app(OrganizationRunService::class)->start($owner->id);
    expect(fn () => $run->getConnection()->transaction(fn () => OrganizationRun::query()->create([
        'user_id' => $owner->id, 'active_user_id' => $owner->id, 'input_revision' => $run->input_revision, 'input_fingerprint' => $run->input_fingerprint,
    ])))->toThrow(QueryException::class);
    expect(OrganizationRun::query()->where('active_user_id', $owner->id)->count())->toBe(1);
});

it('rejects foreign folder and file IDs from generated operations', function (string $foreignKind) {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $file = lifecycleFile($owner);
    $foreignFile = lifecycleFile($other);
    $tree = app(FolderTreeService::class);
    $folder = $tree->ensureFolder($owner->id, 'Building', source: 'system');
    $foreignFolder = $tree->ensureFolder($other->id, 'Building', source: 'system');
    $provider = Mockery::mock(TextAnalysisContract::class);
    $provider->shouldReceive('getProviderName')->andReturn('foreign-fake');
    $provider->shouldReceive('analyze')->once()->andReturn(['operations' => [[
        'type' => 'move', 'folder_id' => null, 'parent_id' => null, 'name' => null,
        'target_id' => $foreignKind === 'folder' ? $foreignFolder->id : $folder->id,
        'file_ids' => [$foreignKind === 'file' ? $foreignFile->id : $file->id], 'confidence' => .95, 'reason' => 'Move',
    ]]]);
    app()->instance(TextAnalysisContract::class, $provider);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    expect(fn () => (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class)))
        ->toThrow(ValidationException::class);
    expect($run->recommendations()->count())->toBe(0)->and($foreignFile->fresh()->primary_folder_id)->toBeNull()
        ->and($foreignFolder->fresh()->name)->toBe('Building');
})->with(['folder', 'file']);

it('applies new folder membership once and undoes it without changing another users placement', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $file = lifecycleFile($owner);
    $foreign = lifecycleFile($other);
    $provider = Mockery::mock(TextAnalysisContract::class);
    $provider->shouldReceive('getProviderName')->andReturn('create-fake');
    $provider->shouldReceive('analyze')->once()->andReturn(['operations' => [[
        'type' => 'create', 'folder_id' => null, 'target_id' => null, 'parent_id' => null, 'name' => 'Building contracts',
        'file_ids' => [$file->id], 'confidence' => .95, 'reason' => 'Group building contracts',
    ]]]);
    app()->instance(TextAnalysisContract::class, $provider);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class));
    $recommendation = $run->recommendations()->first();
    $decisions = app(OrganizationDecisionService::class);
    $decisions->decide($owner->id, [$recommendation->id], 'apply');
    $version = $file->fresh()->placement_version;
    $decisions->decide($owner->id, [$recommendation->id], 'apply');
    expect($file->fresh()->primaryFolder->name)->toBe('Building contracts')->and($file->fresh()->placement_version)->toBe($version);
    $decisions->undo($owner->id, $recommendation->id);
    expect($file->fresh()->primary_folder_id)->toBeNull()->and($file->collections()->count())->toBe(0)
        ->and($foreign->fresh()->primary_folder_id)->toBeNull()->and($runs->start($owner->id))->toBeNull();
});
