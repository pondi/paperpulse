<?php

use App\Http\Resources\Inertia\FileInertiaResource;
use App\Models\BankStatement;
use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\Search\SearchResultFormatter;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->withoutVite();
    $this->owner = User::factory()->create();
    $this->file = File::factory()->create(['user_id' => $this->owner->id, 'fileName' => 'source.pdf']);
});

it('opens every extracted type using the actual file relationship despite overlapping ids', function (string $model, string $type): void {
    $unrelatedFile = File::factory()->create(['user_id' => $this->owner->id]);
    $unrelated = Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $unrelatedFile->id, 'id' => $this->file->id]);
    $entity = $model::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id, 'id' => 100]);
    ExtractableEntity::factory()->create([
        'user_id' => $this->owner->id, 'file_id' => $this->file->id,
        'entity_type' => $type, 'entity_id' => $entity->id, 'is_primary' => true,
    ]);
    $this->actingAs($this->owner)->get(route('files.show', $this->file))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Files/Show')
        ->where('file.id', $this->file->id)->where('file.name', 'source.pdf')
        ->has('extractedEntities', 1)->where('extractedEntities.0.entity_type', $type)
        ->where('extractedEntities.0.entity_id', $entity->id)->where('extractedEntities.0.entity.id', $entity->id));
    expect(FileInertiaResource::forIndex($this->file)->withDetailsUrl()->toArray(request())['detailsUrl'])
        ->toBe(route('files.show', $this->file));
    $this->get(route('documents.show', $unrelated))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('document.id', $unrelated->id));
})->with([
    [Receipt::class, 'receipt'], [Document::class, 'document'], [Invoice::class, 'invoice'],
    [Contract::class, 'contract'], [Voucher::class, 'voucher'], [Warranty::class, 'warranty'],
    [ReturnPolicy::class, 'return_policy'], [BankStatement::class, 'bank_statement'],
]);

it('shows active supplemental entities while excluding deleted and foreign relationships', function (): void {
    foreach (['active', 'deleted', 'foreign'] as $state) {
        $entity = Warranty::factory()->create([
            'user_id' => $state === 'foreign' ? User::factory()->create()->id : $this->owner->id,
            'file_id' => $this->file->id,
        ]);
        ExtractableEntity::factory()->create([
            'user_id' => $this->owner->id, 'file_id' => $this->file->id,
            'entity_type' => 'warranty', 'entity_id' => $entity->id, 'is_primary' => false,
        ]);
        if ($state === 'deleted') {
            $entity->delete();
        }
    }
    $this->actingAs($this->owner)->get(route('files.show', $this->file))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('extractedEntities', 1)
            ->where('extractedEntities.0.entity_type', 'warranty')->where('extractedEntities.0.is_primary', false));
});

it('resolves legacy entities by file id without guessing their numeric ids', function (): void {
    $document = Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id, 'id' => 50]);
    $this->actingAs($this->owner)->get(route('files.show', $this->file))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('hasLegacyData', true)
            ->where('file.primary_document.id', $document->id)->has('extractedEntities', 0));
    $invoice = Invoice::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id, 'id' => 99]);
    ExtractableEntity::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id,
        'entity_type' => 'invoice', 'entity_id' => $invoice->id, 'is_primary' => true]);
    $this->get(route('documents.show', $invoice->id))->assertNotFound();
});

it('allows active file and collection shares and rejects strangers or expired shares', function (string $kind): void {
    $recipient = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id]);
    ExtractableEntity::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id,
        'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true]);
    if ($kind === 'file') {
        $share = FileShare::create(['file_id' => $this->file->id, 'file_type' => 'document',
            'shared_by_user_id' => $this->owner->id, 'shared_with_user_id' => $recipient->id,
            'permission' => 'view', 'shared_at' => now()]);
    } else {
        $collection = Collection::factory()->create(['user_id' => $this->owner->id]);
        $collection->files()->attach($this->file);
        $share = CollectionShare::create(['collection_id' => $collection->id,
            'shared_by_user_id' => $this->owner->id, 'shared_with_user_id' => $recipient->id,
            'permission' => 'view', 'shared_at' => now()]);
    }
    $this->actingAs($recipient)->get(route('files.show', $this->file))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('extractedEntities.0.entity.id', $document->id));
    $this->actingAs(User::factory()->create())->get(route('files.show', $this->file))->assertNotFound();
    $share->update(['expires_at' => now()->subMinute()]);
    $this->actingAs($recipient)->get(route('files.show', $this->file))->assertNotFound();
})->with(['file', 'collection']);

it('uses real destinations for warranty return-policy and statement search results', function (): void {
    $formatter = app(SearchResultFormatter::class);
    foreach ([[Warranty::class, 'formatWarranties'], [ReturnPolicy::class, 'formatReturnPolicies'], [BankStatement::class, 'formatBankStatements']] as [$model, $method]) {
        $entity = $model::factory()->create(['user_id' => $this->owner->id, 'file_id' => $this->file->id, 'id' => 80]);
        $this->actingAs($this->owner);
        $entity->load(['file', 'tags']);
        $result = $formatter->{$method}(collect([$entity]))->first();
        expect($result['url'])->toBe($entity instanceof BankStatement ? route('bank-statements.show', $entity) : route('files.show', $this->file));
        $this->get($result['url'])->assertOk();
    }
});
