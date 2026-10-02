<?php

use App\Contracts\Services\ReceiptParserContract;
use App\Events\FileExtractionCompleted;
use App\Jobs\BankStatements\ProcessCsvImport;
use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Files\ProcessFileGemini;
use App\Jobs\Organization\OrganizeProcessedFile;
use App\Jobs\Receipts\ProcessReceipt;
use App\Listeners\QueueFileOrganization;
use App\Models\Collection;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileOrganizationRequest;
use App\Models\User;
use App\Services\DocumentAnalysisService;
use App\Services\EntityFactory;
use App\Services\FileOrganizationSummaryService;
use App\Services\FolderTreeService;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\Receipts\Analysis\ReceiptCreator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->bus = Bus::getFacadeRoot();
    Bus::fake();
    Http::preventStrayRequests();
});

function organizationExtraction(File $file): void
{
    app(EntityFactory::class)->createEntitiesFromParsedData(['entities' => [['type' => 'contract', 'data' => [
        'contract_title' => 'Employment agreement', 'contract_type' => 'employment',
        'organization' => ['employer_name' => 'Example AS', 'employer_registration' => '123456789',
            'role' => 'contracts', 'confidence' => 0.95],
    ]]]], $file);
    $file->refresh()->update(['status' => 'completed']);
}

function organizationRequest(File $file): FileOrganizationRequest
{
    $generation = $file->meta['processing_generation'] ?? (string) Str::uuid();
    $file->update(['meta' => ['processing_generation' => $generation]]);

    return FileOrganizationRequest::create(['file_id' => $file->id, 'user_id' => $file->user_id,
        'generation' => $generation]);
}

it('queues one owner scoped placement after each ingestion extraction commits', function (string $source, string $class): void {
    $file = File::factory()->create(['status' => 'pending']);
    $jobId = (string) Str::uuid();
    JobMetadataPersistence::store($jobId, ['fileId' => $file->id, 'metadata' => ['source' => $source, 'reprocessing' => $source === 'reprocessing']]);
    $args = $class === ProcessCsvImport::class ? [$jobId, $file->id] : [$jobId];
    $job = Mockery::mock($class, $args)->makePartial()->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('handleJob')->once()->andReturnUsing(fn () => organizationExtraction($file));
    $file->getConnection()->transaction(function () use ($job): void {
        $job->handle();
        Bus::assertNotDispatched(OrganizeProcessedFile::class);
    });
    $job->handle();
    $request = FileOrganizationRequest::sole();
    expect($request->user_id)->toBe($file->user_id)->and($request->generation)->toBe($file->fresh()->meta['processing_generation'])
        ->and($request->status)->toBe('queued')->and($file->fresh()->organization_summary)->toBeNull();
    Bus::assertDispatchedTimes(OrganizeProcessedFile::class, 1);
    Bus::assertDispatched(OrganizeProcessedFile::class, fn ($queued) => $queued->requestId === $request->id && $queued->connection === 'database');
    $placement = new OrganizeProcessedFile($request->id);
    $placement->handle(app(FileOrganizationSummaryService::class));
    $placed = $file->fresh();
    expect($placed->primaryFolder->name)->toBe('Contracts')->and($placed->primaryFolder->parent->parent->name)->toBe('Work');
    $version = $placed->placement_version;
    $placement->handle(app(FileOrganizationSummaryService::class));
    FileExtractionCompleted::dispatch($request->user_id, $file->id, $request->generation);
    expect($file->fresh()->placement_version)->toBe($version)->and(Collection::count())->toBe(3);
    Bus::assertDispatchedTimes(OrganizeProcessedFile::class, 1);
})->with([
    'web' => ['web', ProcessFileGemini::class],
    'API' => ['api', ProcessFileGemini::class],
    'bulk' => ['bulk', ProcessFileGemini::class],
    'scanner' => ['scanner', ProcessReceipt::class],
    'CSV' => ['csv', ProcessCsvImport::class],
    'reprocessing' => ['reprocessing', ProcessFileGemini::class],
]);

it('publishes no placement for failed or superseded extraction', function (): void {
    $file = File::factory()->create(['status' => 'pending']);
    $jobId = (string) Str::uuid();
    JobMetadataPersistence::store($jobId, ['fileId' => $file->id]);
    $job = Mockery::mock(ProcessFileGemini::class, [$jobId])->makePartial()->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('handleJob')->once()->andReturnUsing(function () use ($file): void {
        organizationExtraction($file);
        throw new RuntimeException('Provider failed');
    });
    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'Provider failed');
    expect(FileOrganizationRequest::count())->toBe(0)->and(ExtractableEntity::count())->toBe(0);
    $file->refresh()->update(['meta' => ['processing_generation' => 'newer']]);
    $job->handle();
    Bus::assertNotDispatched(OrganizeProcessedFile::class);
});

it('preserves manual placement and rejects stale generations and foreign owners', function (): void {
    $file = File::factory()->create();
    organizationExtraction($file);
    $request = organizationRequest($file);
    $folder = app(FolderTreeService::class)->ensureFolder($file->user_id, 'Personal');
    $placed = app(FolderTreeService::class)->place($file, $folder, 'manual');
    (new OrganizeProcessedFile($request->id))->handle(app(FileOrganizationSummaryService::class));
    expect($file->fresh()->primary_folder_id)->toBe($folder->id)->and($file->fresh()->placement_version)->toBe($placed->placement_version);
    $request->update(['status' => 'pending', 'generation' => 'old']);
    (new OrganizeProcessedFile($request->id))->handle(app(FileOrganizationSummaryService::class));
    expect($request->fresh()->status)->toBe('obsolete');
    $request->update(['status' => 'pending', 'generation' => $file->meta['processing_generation'], 'user_id' => User::factory()->create()->id]);
    (new OrganizeProcessedFile($request->id))->handle(app(FileOrganizationSummaryService::class));
    expect($request->fresh()->status)->toBe('obsolete')->and($file->fresh()->primary_folder_id)->toBe($folder->id);
});

it('retries placement failures independently of successful extraction', function (): void {
    $file = File::factory()->create();
    organizationExtraction($file);
    $request = organizationRequest($file);
    $summaries = Mockery::mock(FileOrganizationSummaryService::class);
    $summaries->shouldReceive('capture')->once()->andThrow(new RuntimeException('Placement unavailable'));
    expect(fn () => (new OrganizeProcessedFile($request->id))->handle($summaries))->toThrow(RuntimeException::class, 'Placement unavailable');
    expect($file->fresh()->status)->toBe('completed')->and($file->fresh()->primary_folder_id)->toBeNull()
        ->and($request->fresh()->status)->toBe('failed')->and(ExtractableEntity::count())->toBe(1);
    $this->artisan('organization:recover-placements', ['--limit' => 1, '--no-interaction' => true])->assertSuccessful();
    Bus::assertDispatchedTimes(OrganizeProcessedFile::class, 1);
    (new OrganizeProcessedFile($request->id))->handle(app(FileOrganizationSummaryService::class));
    expect($request->fresh()->status)->toBe('completed')->and($file->fresh()->primaryFolder->name)->toBe('Contracts');
});

it('retains durable placement requests through queue outages and recovers abandoned deliveries', function (): void {
    $file = File::factory()->create();
    organizationExtraction($file);
    $request = organizationRequest($file);
    Bus::shouldReceive('dispatch')->once()->with(Mockery::type(OrganizeProcessedFile::class))->andThrow(new RuntimeException('Queue offline'));
    (new QueueFileOrganization)->handle(new FileExtractionCompleted($file->user_id, $file->id, $request->generation));
    expect($request->fresh()->status)->toBe('pending')->and($file->fresh()->status)->toBe('completed');
    Bus::swap($this->bus);
    Bus::fake();
    $this->artisan('organization:recover-placements', ['--no-interaction' => true])->assertSuccessful();
    Bus::assertDispatchedTimes(OrganizeProcessedFile::class, 1);
    FileOrganizationRequest::whereKey($request->id)->update(['updated_at' => now()->subMinutes(11)]);
    $this->artisan('organization:recover-placements', ['--no-interaction' => true])->assertSuccessful();
    Bus::assertDispatchedTimes(OrganizeProcessedFile::class, 2);
});

it('preserves organization evidence from final legacy document analysis', function (): void {
    $file = File::factory()->create(['status' => 'pending']);
    $document = Document::factory()->create(['file_id' => $file->id, 'user_id' => $file->user_id]);
    $jobId = (string) Str::uuid();
    JobMetadataPersistence::store($jobId, ['fileId' => $file->id]);
    $this->mock(DocumentAnalysisService::class)->shouldReceive('analyze')->once()->andReturn([
        'title' => 'Insurance letter', 'organization' => ['property_address' => '12 Birch Road',
            'address_kind' => 'property_subject', 'role' => 'letters', 'confidence' => 0.95],
    ]);
    (new AnalyzeDocument($jobId))->handle();
    $request = FileOrganizationRequest::sole();
    (new OrganizeProcessedFile($request->id))->handle(app(FileOrganizationSummaryService::class));
    expect($file->fresh()->primaryFolder->name)->toBe('Letters')
        ->and($file->fresh()->organization_summary['provenance']['entity_id'])->toBe($document->id);
});

it('links legacy receipt extraction to its owner scoped primary entity', function (): void {
    $file = File::factory()->create();
    $parser = Mockery::mock(ReceiptParserContract::class);
    $parser->shouldReceive('extractDateTime')->once()->andReturn(Carbon::parse('2026-10-01'));
    $receipt = ReceiptCreator::create(['file_id' => $file->id, 'user_id' => $file->user_id,
        'receipt_date' => '2026-10-01', 'total_amount' => 100, 'currency' => 'NOK'], [], $parser);
    expect($file->primaryEntity->entity_id)->toBe($receipt->id)->and($file->primaryEntity->user_id)->toBe($file->user_id);
    $request = organizationRequest($file);
    (new OrganizeProcessedFile($request->id))->handle(app(FileOrganizationSummaryService::class));
    expect($request->fresh()->status)->toBe('completed')->and($file->fresh()->primaryFolder->name)->toBe('Needs review');
});
