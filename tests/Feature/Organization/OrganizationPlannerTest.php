<?php

use App\Contracts\Services\TextAnalysisContract;
use App\Exceptions\AIResponseException;
use App\Jobs\Organization\GenerateOrganizationRecommendations;
use App\Models\File;
use App\Models\OrganizationRecommendation;
use App\Models\User;
use App\Services\AI\Shared\ProcessingUsageBudget;
use App\Services\FolderTreeService;
use App\Services\OrganizationPlanner;
use App\Services\OrganizationRunService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function recommendationFile(int $userId): File
{
    return File::factory()->create(['user_id' => $userId, 'organization_summary' => [
        'version' => 1, 'title' => 'Rental contract', 'property_address' => '12 Birch Road', 'employer' => null,
        'role' => 'contracts', 'dates' => [], 'confidence' => .95,
    ]]);
}

function fakeOrganizationAnalysis(array $operations, int $calls = 1): void
{
    $mock = Mockery::mock(TextAnalysisContract::class);
    $mock->shouldReceive('getProviderName')->andReturn('fake');
    $mock->shouldReceive('analyze')->times($calls)->withArgs(function (string $prompt, array $schema): bool {
        expect(strlen($prompt))->toBeLessThanOrEqual(config('ai.organization.max_prompt_bytes'));
        expect($prompt)->not->toContain('extracted_text')->toContain('untrusted document data');
        expect($schema['properties']['operations']['items']['properties']['type']['enum'])->toBe(['create', 'rename', 'merge', 'move']);

        return true;
    })->andReturnUsing(function (string $prompt) use ($operations): array {
        ProcessingUsageBudget::reserve(strlen($prompt) + 100);
        ProcessingUsageBudget::record(50, 50);

        return ['operations' => $operations];
    });
    app()->instance(TextAnalysisContract::class, $mock);
}

it('persists bounded recommendations and measured usage through an owner scoped database job', function () {
    Queue::fake();
    $owner = User::factory()->create();
    recommendationFile($owner->id);
    $folder = app(FolderTreeService::class)->ensureFolder($owner->id, 'Building');
    fakeOrganizationAnalysis([['type' => 'create', 'folder_id' => null, 'target_id' => null,
        'parent_id' => $folder->id, 'file_ids' => [], 'name' => '12 Birch Road', 'confidence' => .95, 'reason' => 'Keep building contracts together']]);
    $run = app(OrganizationRunService::class)->start($owner->id);
    $job = new GenerateOrganizationRecommendations($owner->id, $run->id);
    expect($job->connection)->toBe('database');
    $job->handle(app(OrganizationRunService::class), app(OrganizationPlanner::class));
    expect($run->fresh()->status)->toBe('awaiting_decisions')->and($run->fresh()->calls)->toBe(1)
        ->and($run->fresh()->tokens)->toBeLessThan(config('ai.organization.max_tokens'));
    expect($run->recommendations()->first()->operation['parent_id'])->toBe($folder->id);
    $job->handle(app(OrganizationRunService::class), app(OrganizationPlanner::class));
    expect($run->recommendations()->count())->toBe(1);
});

it('closes empty plans and makes no calls for unchanged inputs', function () {
    $file = recommendationFile(User::factory()->create()->id);
    fakeOrganizationAnalysis([]);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($file->user_id);
    (new GenerateOrganizationRecommendations($file->user_id, $run->id))->handle($runs, app(OrganizationPlanner::class));
    expect($run->fresh()->status)->toBe('completed')->and($runs->start($file->user_id))->toBeNull();
});

it('rejects foreign identifiers invalid operations and executable paths before persistence', function (array $changes) {
    $owner = User::factory()->create();
    recommendationFile($owner->id);
    $folder = app(FolderTreeService::class)->ensureFolder($owner->id, 'Building');
    $foreign = app(FolderTreeService::class)->ensureFolder(User::factory()->create()->id, 'Foreign');
    $operation = ['type' => 'create', 'folder_id' => null, 'target_id' => null, 'parent_id' => $folder->id,
        'file_ids' => [], 'name' => 'Building contracts', 'confidence' => .95, 'reason' => 'Group contracts'];
    if ($changes === ['foreign']) {
        $changes = ['parent_id' => $foreign->id];
    }
    fakeOrganizationAnalysis([array_replace($operation, $changes)]);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    expect(fn () => (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class)))
        ->toThrow(ValidationException::class);
    expect(OrganizationRecommendation::query()->count())->toBe(0)->and($run->fresh()->status)->toBe('failed');
})->with([[['foreign']], [['type' => 'delete']], [['name' => '../../private']], [['command' => 'sh']], [['type' => 'move']]]);

it('chunks an archive in one run and recovers progress without exceeding the call budget', function () {
    config(['ai.organization.chunk_size' => 1, 'ai.organization.max_calls' => 1]);
    $owner = User::factory()->create();
    $first = recommendationFile($owner->id);
    recommendationFile($owner->id);
    fakeOrganizationAnalysis([], 2);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    expect(fn () => (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class)))
        ->toThrow(AIResponseException::class);
    expect($run->fresh()->cursor)->toBe($first->id)->and($run->fresh()->calls)->toBe(1)->and($run->fresh()->status)->toBe('failed');
});
