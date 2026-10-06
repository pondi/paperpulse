<?php

use App\Models\Document;
use App\Models\File;
use App\Models\User;
use App\Services\AI\Extractors\Contract\ContractDataNormalizer;
use App\Services\AI\Extractors\Contract\ContractSchema;
use App\Services\AI\Extractors\Document\DocumentDataNormalizer;
use App\Services\AI\Extractors\Document\DocumentExtractor;
use App\Services\AI\Extractors\Document\DocumentSchema;
use App\Services\AI\Prompt\Schema\DocumentPromptSchemaProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\EntityFactory;
use App\Services\FileOrganizationSummaryService;
use App\Services\OrganizationSummaryNormalizer;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Bus::fake();
});

test('existing extraction produces a bounded primary summary with exactly one provider request', function () {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);
    $this->mock(GeminiProvider::class)->shouldReceive('analyzeFileByUri')->once()->andReturn(['data' => [
        'document_title' => str_repeat('Long title ', 30), 'document_type' => 'letter',
        'creation_date' => '2026-10-01', 'tags' => ['insurance'], 'confidence_score' => 0.95,
        'organization' => ['subject' => 'Building insurance', 'property_address' => '  12   Birch Road ',
            'address_kind' => 'property_subject', 'role' => 'letters', 'confidence' => 0.91,
            'keywords' => array_map(fn ($number) => str_repeat('keyword'.$number, 10), range(1, 20))],
    ]]);
    $result = app(DocumentExtractor::class)->extract('https://provider/file', $file);
    app(EntityFactory::class)->createEntitiesFromParsedData(['entities' => [
        ['type' => 'document', 'data' => $result['data']],
        ['type' => 'document', 'data' => ['title' => 'Supplemental document']],
    ]], $file);
    expect($file->fresh()->organization_summary)->toBeNull();
    expect(mb_strlen($file->primaryEntity->entity->title))->toBe(255)
        ->and($file->primaryEntity->entity->metadata['title'])->toBe(str_repeat('Long title ', 30));
    app(FileOrganizationSummaryService::class)->capture($file, $file->primaryEntity->entity);
    $summary = $file->fresh()->organization_summary;
    expect(mb_strlen($summary['title']))->toBe(120)
        ->and($summary['version'])->toBe(1)->and($summary['property_address'])->toBe('12 Birch Road')
        ->and($summary['role'])->toBe('letters')->and($summary['confidence'])->toBe(0.91)
        ->and($summary['keywords'])->toHaveCount(8)->and(mb_strlen($summary['keywords'][0]))->toBe(32)
        ->and($summary['dates']['creation_date'])->toBe('2026-10-01')
        ->and($summary['provenance']['source'])->toBe('existing_extraction');
    expect($summary['provenance']['entity_id'])->toBe($file->primaryEntity->entity_id);
});

test('contract employer evidence survives normalization and generic factory persistence', function () {
    $file = File::factory()->create();
    $data = app(ContractDataNormalizer::class)->normalize([
        'contract_title' => 'Employment agreement', 'contract_type' => 'Employment',
        'effective_date' => '2026-01-01', 'confidence_score' => 0.94,
        'parties' => [['name' => 'Example AS', 'role' => 'employer', 'registration_number' => '123456789']],
        'organization' => ['employer_name' => 'Example AS', 'employer_registration' => '123456789',
            'role' => 'contracts', 'confidence' => 0.93],
    ]);
    app(EntityFactory::class)->createEntitiesFromParsedData(['entities' => [['type' => 'contract', 'data' => $data]]], $file);
    app(FileOrganizationSummaryService::class)->capture($file, $file->primaryEntity->entity);
    expect($file->fresh()->organization_summary)->toMatchArray([
        'employer' => ['name' => 'Example AS', 'registration' => '123456789'],
        'role' => 'contracts', 'confidence' => 0.93,
    ]);
});

test('issuer merchant and incidental addresses cannot become a subject property', function (string $kind) {
    $summary = app(OrganizationSummaryNormalizer::class)->normalize('document', [
        'title' => 'Correspondence', 'organization' => ['property_address' => 'Wrong address', 'address_kind' => $kind, 'confidence' => 1],
        'issuer_address' => 'Another address', 'entities_mentioned' => [['entity_name' => 'Mentioned company', 'entity_type' => 'organization']],
    ]);
    expect($summary['property_address'])->toBeNull()->and($summary['employer'])->toBeNull();
})->with(['issuer', 'merchant', 'incidental', 'unknown']);

test('invalid organization evidence stays conservative while typed employer parties remain usable', function () {
    $normalizer = app(OrganizationSummaryNormalizer::class);
    $invalid = $normalizer->normalize('document', ['title' => ['bad'], 'organization' => [
        'property_address' => ['bad'], 'address_kind' => 'property_subject', 'role' => 'delete all', 'confidence' => 5,
        'keywords' => [false, ['bad'], 'Insurance', 'insurance']], 'creation_date' => '2026-02-31']);
    expect($invalid['title'])->toBe('Document')->and($invalid['property_address'])->toBeNull()
        ->and($invalid['role'])->toBe('other')->and($invalid['confidence'])->toBe(0.0)
        ->and($invalid['dates'])->toBe([])->and($invalid['keywords'])->toHaveCount(1);
    $legacy = $normalizer->normalize('contract', ['contract_title' => 'Employment agreement',
        'parties' => [['name' => 'Employer AS', 'role' => 'employer']], 'confidence_score' => 0.9]);
    expect($legacy['employer']['name'])->toBe('Employer AS')->and($legacy['confidence'])->toBe(0.9)
        ->and($legacy['provenance']['evidence'])->toBe('typed_employer_party');
});

test('summary capture rejects foreign owner or foreign file evidence', function () {
    $file = File::factory()->create();
    $other = File::factory()->create();
    $document = Document::factory()->create(['user_id' => $other->user_id, 'file_id' => $other->id]);
    expect(fn () => app(FileOrganizationSummaryService::class)->capture($file, $document))->toThrow(ValidationException::class);
    expect($file->fresh()->organization_summary)->toBeNull();
});

test('active and specialized schemas use one optional organization evidence contract', function () {
    $schema = DocumentPromptSchemaProvider::schema();
    expect($schema['properties']['organization'])->toBe(DocumentSchema::get()['responseSchema']['properties']['organization'])
        ->toBe(ContractSchema::get()['responseSchema']['properties']['organization']);
    expect($schema['required'])->not->toContain('organization');
});

it('preserves generic context paths through extraction normalization entity persistence and summary capture', function (): void {
    $file = File::factory()->create();
    $path = [
        ['kind' => 'project', 'name' => 'Example restoration', 'relationship' => 'subject', 'confidence' => .95],
        ['kind' => 'category', 'name' => 'Permits', 'relationship' => 'subgroup', 'confidence' => .95],
    ];
    $data = app(DocumentDataNormalizer::class)->normalize([
        'document_title' => 'Permit confirmation', 'document_type' => 'letter',
        'organization' => ['group_path' => $path, 'confidence' => .95],
    ]);
    app(EntityFactory::class)->createEntitiesFromParsedData(['entities' => [['type' => 'document', 'data' => $data]]], $file);
    app(FileOrganizationSummaryService::class)->capture($file, $file->primaryEntity->entity);
    expect($file->fresh()->organization_summary['group_path'][0]['kind'])->toBe('project')
        ->and($file->fresh()->primaryFolder->name)->toBe('Permits')
        ->and($file->fresh()->primaryFolder->parent->name)->toBe('Example restoration');
});
