<?php

use App\Contracts\Services\TextAnalysisContract;
use App\Jobs\Organization\GenerateOrganizationRecommendations;
use App\Models\File;
use App\Models\OrganizationRecommendation;
use App\Models\User;
use App\Services\AI\Shared\ProcessingUsageBudget;
use App\Services\FolderTreeService;
use App\Services\OrganizationPlanner;
use App\Services\OrganizationRunService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Exceptions;
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

it('continues large archives in queued segments without exhausting the archive call budget', function (): void {
    Queue::fake();
    config(['ai.organization.chunk_size' => 1, 'ai.organization.max_calls' => 1]);
    $owner = User::factory()->create();
    $first = recommendationFile($owner->id);
    $second = recommendationFile($owner->id);
    fakeOrganizationAnalysis([], 2);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    $job = new GenerateOrganizationRecommendations($owner->id, $run->id);
    $job->handle($runs, app(OrganizationPlanner::class));
    expect($run->fresh()->cursor)->toBe($first->id)->and($run->fresh()->calls)->toBe(1)
        ->and($run->fresh()->status)->toBe('queued')->and($run->fresh()->attempts)->toBe(0);
    $job->handle($runs, app(OrganizationPlanner::class));
    expect($run->fresh()->cursor)->toBe($second->id)->and($run->fresh()->calls)->toBe(2)
        ->and($run->fresh()->status)->toBe('completed');
    Queue::assertPushed(GenerateOrganizationRecommendations::class, 2);
});

it('ignores unsupported parent-folder merges without failing valid recommendations in the same response', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $file = recommendationFile($owner->id);
    $tree = app(FolderTreeService::class);
    $source = $tree->ensureFolder($owner->id, 'Old property', type: 'property', source: 'system');
    $target = $tree->ensureFolder($owner->id, 'Property', type: 'property', source: 'system');
    $tree->ensureFolder($owner->id, 'Invoices', $source->id, 'document_role', 'system');
    fakeOrganizationAnalysis([
        ['type' => 'merge', 'folder_id' => $source->id, 'target_id' => $target->id, 'parent_id' => null,
            'file_ids' => [], 'name' => null, 'confidence' => .95, 'reason' => 'Same property'],
        ['type' => 'create', 'folder_id' => null, 'target_id' => null, 'parent_id' => $target->id,
            'file_ids' => [$file->id], 'name' => 'Contracts', 'confidence' => .95, 'reason' => 'Group contracts'],
    ]);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class));
    expect($run->fresh()->status)->toBe('awaiting_decisions')->and($run->recommendations()->count())->toBe(1)
        ->and($run->recommendations()->sole()->operation['type'])->toBe('create');
});

it('defers an exhausted daily allowance and resumes from its saved cursor instead of failing the archive', function (): void {
    Queue::fake();
    config(['ai.organization.chunk_size' => 1, 'ai.limits.max_calls_per_user_day' => 1]);
    $owner = User::factory()->create();
    $first = recommendationFile($owner->id);
    recommendationFile($owner->id);
    fakeOrganizationAnalysis([], 2);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class));
    expect($run->fresh()->status)->toBe('queued')->and($run->fresh()->cursor)->toBe($first->id)
        ->and($run->fresh()->calls)->toBe(1)->and($run->fresh()->attempts)->toBe(0);
    Queue::assertPushed(GenerateOrganizationRecommendations::class, fn ($job): bool => $job->delay !== null && $job->delay->isFuture());
});

it('evicts invalid cached planner output so an automatic retry can receive a corrected response', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    recommendationFile($owner->id);
    $provider = Mockery::mock(TextAnalysisContract::class);
    $provider->shouldReceive('getProviderName')->andReturn('invalid-cache-fake');
    $provider->shouldReceive('analyze')->once()->andReturn(['operations' => [['type' => 'delete']]]);
    $provider->shouldReceive('analyze')->once()->andReturn(['operations' => []]);
    app()->instance(TextAnalysisContract::class, $provider);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    $job = new GenerateOrganizationRecommendations($owner->id, $run->id);
    expect(fn () => $job->handle($runs, app(OrganizationPlanner::class)))->toThrow(ValidationException::class);
    $job->handle($runs, app(OrganizationPlanner::class));
    expect($run->fresh()->status)->toBe('completed')->and($run->fresh()->attempts)->toBe(2);
});

it('automatically groups old summaries by supported people and subgroups in one compact archive request', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $files = collect(['Enrollment confirmation', 'Course confirmation'])->map(fn (string $title): File => File::factory()->create([
        'user_id' => $owner->id, 'organization_summary' => ['version' => 1, 'title' => $title,
            'subject' => 'Education for Alex Example', 'role' => 'other', 'confidence' => .95],
    ]));
    $path = [
        ['kind' => 'person', 'name' => 'Alex Example', 'relationship' => 'subject', 'confidence' => .95],
        ['kind' => 'category', 'name' => 'Education', 'relationship' => 'subgroup', 'confidence' => .95],
    ];
    $mock = Mockery::mock(TextAnalysisContract::class);
    $mock->shouldReceive('getProviderName')->andReturn('fake');
    $mock->shouldReceive('analyze')->once()->withArgs(function (string $prompt, array $schema): bool {
        expect($prompt)->toContain('Education for Alex Example', 'applied automatically')->not->toContain('extracted_text');
        expect($schema['properties']['assignments']['items']['properties'])->toHaveKeys(['file_id', 'group_path']);

        return true;
    })->andReturn(['operations' => [], 'assignments' => $files->map(fn (File $file): array => ['file_id' => $file->id, 'group_path' => $path])->all()]);
    app()->instance(TextAnalysisContract::class, $mock);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($owner->id);
    (new GenerateOrganizationRecommendations($owner->id, $run->id))->handle($runs, app(OrganizationPlanner::class));
    expect($run->fresh()->status)->toBe('completed')->and($run->recommendations()->count())->toBe(0)
        ->and($files->map(fn (File $file): int => $file->fresh()->primary_folder_id)->unique())->toHaveCount(1);
    $folder = $files->first()->fresh()->primaryFolder;
    expect($folder->name)->toBe('Education')->and($folder->parent->name)->toBe('Alex Example');
});

it('does not apply weak archive context assignments or accept a foreign assignment identifier', function (bool $foreign): void {
    Queue::fake();
    $file = recommendationFile(User::factory()->create()->id);
    $mock = Mockery::mock(TextAnalysisContract::class);
    $mock->shouldReceive('getProviderName')->andReturn('fake');
    $mock->shouldReceive('analyze')->once()->andReturn(['operations' => [], 'assignments' => [[
        'file_id' => $foreign ? File::factory()->create()->id : $file->id,
        'group_path' => [['kind' => 'person', 'name' => 'Alex Example', 'relationship' => 'subject', 'confidence' => .4]],
    ]]]);
    app()->instance(TextAnalysisContract::class, $mock);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($file->user_id);
    $handle = fn () => (new GenerateOrganizationRecommendations($file->user_id, $run->id))->handle($runs, app(OrganizationPlanner::class));
    if ($foreign) {
        expect($handle)->toThrow(ValidationException::class);
    } else {
        $handle();
    }
    expect($file->fresh()->organization_summary)->not->toHaveKey('group_path')
        ->and($file->fresh()->primary_folder_id)->toBeNull();
})->with([true, false]);

it('reports invalid recommendations as production issues with the archive run context', function (): void {
    Queue::fake();
    Exceptions::fake();
    $file = recommendationFile(User::factory()->create()->id);
    fakeOrganizationAnalysis([['type' => 'delete', 'file_ids' => [], 'confidence' => .95, 'reason' => 'Unsupported operation']]);
    $runs = app(OrganizationRunService::class);
    $run = $runs->start($file->user_id);
    expect(fn () => (new GenerateOrganizationRecommendations($file->user_id, $run->id))->handle($runs, app(OrganizationPlanner::class)))
        ->toThrow(ValidationException::class);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => str_contains($exception->getMessage(), 'Folder planner returned invalid recommendations'));
    expect(Context::get('organization')['run_id'])->toBe($run->id)
        ->and(Context::get('organization')['user_id'])->toBe($file->user_id);
});
