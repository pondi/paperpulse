<?php

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\Search\SearchFacetService;
use App\Services\Search\SearchQueryBuilder;
use App\Services\Search\SearchResultFormatter;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->searchService = app(SearchService::class);
});

it('serializes the persisted uploaded filename in search results', function (string $model, string $method): void {
    $file = File::factory()->create(['user_id' => $this->user->id, 'fileName' => 'uploaded-report.pdf']);
    $entity = $model::factory()->create(['user_id' => $this->user->id, 'file_id' => $file->id]);
    $result = app(SearchResultFormatter::class)->{$method}(collect([$entity]))->first();

    expect($file->fresh()->fileName)->toBe('uploaded-report.pdf')
        ->and($file->isFillable('original_filename'))->toBeFalse()
        ->and($result['filename'])->toBe('uploaded-report.pdf')
        ->and($result['file']['filename'])->toBe('uploaded-report.pdf');

    if ($entity instanceof Document) {
        expect($entity->toSearchableArray()['file_name'])->toBe('uploaded-report.pdf');
    }
})->with([
    'receipt' => [Receipt::class, 'formatReceipts'],
    'document' => [Document::class, 'formatDocuments'],
    'invoice' => [Invoice::class, 'formatInvoices'],
    'contract' => [Contract::class, 'formatContracts'],
    'voucher' => [Voucher::class, 'formatVouchers'],
    'warranty' => [Warranty::class, 'formatWarranties'],
    'return policy' => [ReturnPolicy::class, 'formatReturnPolicies'],
    'bank statement' => [BankStatement::class, 'formatBankStatements'],
]);

// ─── Type filtering ──────────────────────────────────────────────

it('returns empty results when query is empty and no filters', function () {
    $result = $this->searchService->search('');

    expect($result['results'])->toBeEmpty()
        ->and($result['facets']['total'])->toBe(0);
});

it('searches invoices and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);

    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'from_name' => 'Visma Solutions',
        'invoice_number' => 'INV-99999',
        'total_amount' => 1500.00,
        'currency' => 'NOK',
    ]);

    // Search by invoice_number which is in toSearchableArray
    $result = $this->searchService->search('INV-99999', ['type' => 'invoice']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('invoice')
        ->and($result['results'][0]['title'])->toBe('Visma Solutions')
        ->and($result['results'][0]['total'])->toBe('1500.00')
        ->and($result['results'][0]['currency'])->toBe('NOK');
});

it('searches contracts and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'contract',
        'processing_type' => 'contract',
    ]);

    Contract::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'contract_title' => 'Service Agreement Alpha',
        'contract_type' => 'service',
    ]);

    $result = $this->searchService->search('Alpha', ['type' => 'contract']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('contract')
        ->and($result['results'][0]['title'])->toBe('Service Agreement Alpha');
});

it('searches vouchers and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'voucher',
        'processing_type' => 'voucher',
    ]);

    Voucher::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'code' => 'SUMMER-DEAL-50',
        'current_value' => 500.00,
        'currency' => 'NOK',
    ]);

    $result = $this->searchService->search('SUMMER', ['type' => 'voucher']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('voucher')
        ->and($result['results'][0]['title'])->toBe('SUMMER-DEAL-50');
});

it('searches warranties and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'warranty',
        'processing_type' => 'warranty',
    ]);

    Warranty::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'product_name' => 'Samsung Galaxy S24',
        'manufacturer' => 'Samsung',
    ]);

    $result = $this->searchService->search('Samsung', ['type' => 'warranty']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('warranty')
        ->and($result['results'][0]['title'])->toBe('Samsung Galaxy S24');
});

it('searches return policies and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'return_policy',
        'processing_type' => 'return_policy',
    ]);

    ReturnPolicy::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'conditions' => 'Full refund within 30 days',
        'refund_method' => 'original_payment',
    ]);

    // Search by refund_method which is in toSearchableArray
    $result = $this->searchService->search('original_payment', ['type' => 'return_policy']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('return_policy');
});

it('searches bank statements and returns them with correct type', function () {
    $file = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'bank_statement',
        'processing_type' => 'bank_statement',
    ]);

    BankStatement::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $file->id,
        'bank_name' => 'DNB Bank ASA',
        'closing_balance' => 25000.00,
        'currency' => 'NOK',
    ]);

    $result = $this->searchService->search('DNB', ['type' => 'bank_statement']);

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['type'])->toBe('bank_statement')
        ->and($result['results'][0]['title'])->toBe('DNB Bank ASA');
});

// ─── Type filtering isolates correctly ───────────────────────────

it('type filter excludes other entity types', function () {
    $invoiceFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $invoiceFile->id,
        'invoice_number' => 'FILTERTEST-001',
    ]);

    $contractFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'contract',
        'processing_type' => 'contract',
    ]);
    Contract::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $contractFile->id,
        'contract_title' => 'FILTERTEST Agreement',
    ]);

    // Searching with type=invoice should not return contracts
    $result = $this->searchService->search('FILTERTEST', ['type' => 'invoice']);
    $types = array_column($result['results'], 'type');

    expect($types)->not->toBeEmpty()
        ->and($types)->each->toBe('invoice');
});

// ─── Facets include all types ────────────────────────────────────

it('facets include counts for all entity types', function () {
    $invoiceFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $invoiceFile->id,
        'from_name' => 'Facet Test Company',
    ]);

    $contractFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'contract',
        'processing_type' => 'contract',
    ]);
    Contract::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $contractFile->id,
        'contract_title' => 'Facet Test Contract',
    ]);

    $result = $this->searchService->search('Facet');

    expect($result['facets'])->toHaveKeys([
        'total', 'receipts', 'documents', 'invoices', 'contracts',
        'vouchers', 'warranties', 'return_policies', 'bank_statements',
    ]);
});

// ─── All types appear in unfiltered search ───────────────────────

it('all type results appear in unfiltered search', function () {
    $invoiceFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $invoiceFile->id,
        'invoice_number' => 'XUNIQ-77777',
    ]);

    $contractFile = File::factory()->create([
        'user_id' => $this->user->id,
        'file_type' => 'contract',
        'processing_type' => 'contract',
    ]);
    Contract::factory()->create([
        'user_id' => $this->user->id,
        'file_id' => $contractFile->id,
        'contract_title' => 'XUNIQ Agreement',
    ]);

    // Search for something that matches both indexed fields
    $result = $this->searchService->search('XUNIQ', ['type' => 'all']);
    $types = array_unique(array_column($result['results'], 'type'));

    expect($types)->toContain('invoice')
        ->and($types)->toContain('contract');
});

// ─── User isolation ──────────────────────────────────────────────

it('does not return other users entities', function () {
    $otherUser = User::factory()->create();

    $file = File::factory()->create([
        'user_id' => $otherUser->id,
        'file_type' => 'invoice',
        'processing_type' => 'invoice',
    ]);
    Invoice::factory()->create([
        'user_id' => $otherUser->id,
        'file_id' => $file->id,
        'invoice_number' => 'OTHERUSER-999',
    ]);

    $result = $this->searchService->search('OTHERUSER-999', ['type' => 'invoice']);

    expect($result['results'])->toBeEmpty();
});

// ─── API validation ──────────────────────────────────────────────

it('api search accepts new entity types as type filter', function () {
    $this->actingAs($this->user);

    $types = ['invoice', 'contract', 'voucher', 'warranty', 'return_policy', 'bank_statement'];

    foreach ($types as $type) {
        $response = $this->getJson('/api/v1/search?q=test&type='.$type);
        $response->assertSuccessful();
    }
});

it('api search rejects invalid type filter', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/api/v1/search?q=test&type=invalid_type');
    $response->assertStatus(422);
});

it('merges bounded search pages with stable cross-type ranking and exact counts', function (int $page, array $expected) {
    $query = 'Åse Østgårds vei 12';
    $filters = ['limit' => 2, 'page' => $page];
    $builder = Mockery::mock(SearchQueryBuilder::class);
    $builder->shouldReceive('searchReceipts')->once()->with($query, $filters)->andReturn([
        'results' => collect([
            ['id' => 2, 'type' => 'receipt', '_rankingScore' => 0.8],
            ['id' => 1, 'type' => 'receipt', '_rankingScore' => 0.95],
        ]), 'total' => 2,
    ]);
    $builder->shouldReceive('searchDocuments')->once()->with($query, $filters)->andReturn([
        'results' => collect([['id' => 3, 'type' => 'document', '_rankingScore' => 0.95]]), 'total' => 1,
    ]);
    foreach (['searchInvoices', 'searchContracts', 'searchVouchers', 'searchWarranties', 'searchReturnPolicies', 'searchBankStatements'] as $method) {
        $builder->shouldReceive($method)->once()->with($query, $filters)->andReturn(['results' => collect(), 'total' => 0]);
    }
    $facets = Mockery::mock(SearchFacetService::class);
    $facets->shouldReceive('buildFacets')->once()->andReturn([]);
    $response = (new SearchService($builder, $facets))->search($query, $filters);
    expect(array_column($response['results'], 'id'))->toBe($expected)
        ->and($response['pagination'])->toBe(['page' => $page, 'per_page' => 2, 'total' => 3, 'last_page' => 2]);
})->with([[1, [3, 1]], [2, [2]]]);

it('rejects search input beyond bounded query and page limits', function (array $query, string $error) {
    $this->getJson('/search?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    [['query' => str_repeat('a', 201)], 'query'],
    [['query' => 'test', 'page' => 21], 'page'],
    [['query' => 'test', 'limit' => 51], 'limit'],
]);

it('reports unavailable indexes and preserves successful partial results', function (string $type, string $status) {
    $builder = Mockery::mock(SearchQueryBuilder::class);
    $builder->shouldReceive('searchReceipts')->once()->andThrow(new RuntimeException('Index unavailable'));
    if ($type === 'all') {
        $builder->shouldReceive('searchDocuments')->once()->andReturn(['results' => collect([['id' => 1, 'type' => 'document', '_rankingScore' => 1]]), 'total' => 1]);
        foreach (['searchInvoices', 'searchContracts', 'searchVouchers', 'searchWarranties', 'searchReturnPolicies', 'searchBankStatements'] as $method) {
            $builder->shouldReceive($method)->once()->andReturn(['results' => collect(), 'total' => 0]);
        }
    }
    $facets = Mockery::mock(SearchFacetService::class);
    $facets->shouldReceive('buildFacets')->once()->andThrow(new RuntimeException('Facet index unavailable'));
    $response = (new SearchService($builder, $facets))->search('test', ['type' => $type]);
    expect($response['search_status'])->toBe($status)->and($response['unavailable_types'])->toBe(['receipt'])
        ->and($response['facets'])->toBeNull()->and($response['results'])->toHaveCount($type === 'all' ? 1 : 0);
})->with([['receipt', 'unavailable'], ['all', 'partial']]);

it('retries facet failures without caching an empty success', function () {
    $facets = Mockery::mock(SearchFacetService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $facets->shouldReceive('computeFacets')->once()->ordered()->andThrow(new RuntimeException('Index unavailable'));
    $facets->shouldReceive('computeFacets')->once()->ordered()->andReturn(['total' => 1, 'receipts' => 1]);
    expect(fn () => $facets->buildFacets('retry facets', []))->toThrow(RuntimeException::class)
        ->and($facets->buildFacets('retry facets', []))->toBe(['total' => 1, 'receipts' => 1])
        ->and($facets->buildFacets('retry facets', []))->toBe(['total' => 1, 'receipts' => 1]);
});

it('finds invoices by displayed sender with or without a linked merchant', function (bool $hasMerchant): void {
    $merchant = $hasMerchant ? Merchant::create(['user_id' => $this->user->id, 'name' => 'Billing intermediary']) : null;
    $invoice = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'merchant_id' => $merchant?->id,
        'from_name' => 'Distinct sender name',
    ]);
    $other = User::factory()->create();
    $otherFile = File::factory()->create(['user_id' => $other->id]);
    Invoice::factory()->create(['user_id' => $other->id, 'file_id' => $otherFile->id, 'from_name' => 'Distinct sender name']);

    $result = $this->searchService->search('Distinct sender name', ['type' => 'invoice']);
    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['id'])->toBe($invoice->id)
        ->and($invoice->toSearchableArray()['from_name'])->toBe('Distinct sender name')
        ->and(config('scout.meilisearch.index-settings.'.Invoice::class.'.searchableAttributes'))->toContain('from_name');
})->with([true, false]);
