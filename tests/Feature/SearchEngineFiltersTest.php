<?php

use App\Models\BankStatement;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Contract;
use App\Models\Document;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\Search\SearchFilterBuilder;
use App\Services\Search\SearchQueryBuilder;
use Meilisearch\Client;

it('applies inclusive engine date and amount ranges with tenant isolation', function (string $model, string $method, string $date, ?string $amount) {
    if (! getenv('MEILISEARCH_TEST_HOST')) {
        $this->markTestSkipped('Set MEILISEARCH_TEST_HOST for the isolated engine test.');
    }
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $entities = collect();
    $label = 'Books" OR user_id = 999 OR category_name = "Other';
    $collection = Collection::factory()->create(['user_id' => $owner->id]);
    foreach ([[$owner, '2026-02-01', 10], [$owner, '2026-02-28', 20], [$owner, '2026-03-01', 30], [$other, '2026-02-10', 15]] as [$user, $day, $value]) {
        $this->actingAs($user);
        $file = File::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
        $attributes = ['user_id' => $user->id, 'file_id' => $file->id, $date => $day];
        if ($amount) {
            $attributes[$amount] = $value;
            $attributes['currency'] = $value === 10 ? 'EUR' : 'USD';
        }
        if (in_array($model, [Receipt::class, Document::class, Invoice::class], true)) {
            $category = Category::firstOrCreate(['user_id' => $user->id, 'name' => $value === 30 ? 'Other' : $label], ['slug' => $user->id.'-'.($value === 30 ? 'other' : 'books')]);
            $attributes['category_id'] = $category->id;
            if ($model === Receipt::class) {
                $attributes['receipt_category'] = $category->name;
            }
        }
        if ($model === Contract::class) {
            $attributes['contract_title'] = 'Åse Østgårds vei 12';
        }
        $entities->push($model::factory()->create($attributes));
        if ($entities->count() === 1) {
            $file->collections()->attach($collection);
        }
    }
    $this->actingAs($owner);
    $prefix = 'todo_filters_'.bin2hex(random_bytes(6)).'_';
    config(['scout.driver' => 'meilisearch', 'scout.prefix' => $prefix, 'scout.meilisearch.host' => getenv('MEILISEARCH_TEST_HOST'), 'scout.meilisearch.key' => null]);
    $client = new Client(config('scout.meilisearch.host'));
    $index = $client->index($entities->first()->searchableAs());
    try {
        $task = $client->createIndex($index->getUid(), ['primaryKey' => 'id']);
        $client->waitForTask($task['taskUid']);
        $task = $index->updateSettings(config('scout.meilisearch.index-settings.'.$model));
        expect($client->waitForTask($task['taskUid'])['status'])->toBe('succeeded');
        $task = $index->addDocuments($entities->map->toSearchableArray()->all());
        expect($client->waitForTask($task['taskUid'])['status'])->toBe('succeeded');
        if ($model === Contract::class) {
            $page = app(SearchQueryBuilder::class)->{$method}('Åse Østgårds vei 12', ['limit' => 1, 'page' => 2]);
            expect($page['results'])->toHaveCount(2)->and($page['total'])->toBe(3);
        }
        $filters = ['date_from' => '2026-02-01', 'date_to' => '2026-02-28'];
        if ($amount) {
            $filters += ['amount_min' => 10, 'amount_max' => 20];
        }
        $raw = SearchFilterBuilder::apply($model::search('')->where('user_id', $owner->id), $filters)->raw();
        expect(collect($raw['hits'])->pluck('id')->sort()->values()->all())->toBe($entities->take(2)->pluck('id')->sort()->values()->all());
        $results = app(SearchQueryBuilder::class)->{$method}('', $filters)['results'];
        expect(app(SearchQueryBuilder::class)->{$method}('', ['collection_id' => $collection->id])['results']->pluck('id')->all())->toBe([$entities[0]->id]);
        if (in_array($model, [Receipt::class, Document::class, Invoice::class], true)) {
            expect(app(SearchQueryBuilder::class)->{$method}('', ['category' => $label])['results']->pluck('id')->sort()->values()->all())->toBe($entities->take(2)->pluck('id')->sort()->values()->all());
        }
        expect($results->pluck('id')->sort()->values()->all())->toBe($entities->take(2)->pluck('id')->sort()->values()->all());
        if ($amount) {
            expect(app(SearchQueryBuilder::class)->{$method}('', ['amount_min' => 20, 'amount_max' => 20])['results']->pluck('id')->all())->toBe([$entities[1]->id]);
        }
    } finally {
        $client->deleteIndex($index->getUid());
    }
})->with([
    [Receipt::class, 'searchReceipts', 'receipt_date', 'total_amount'],
    [Document::class, 'searchDocuments', 'document_date', null],
    [Invoice::class, 'searchInvoices', 'invoice_date', 'total_amount'],
    [Contract::class, 'searchContracts', 'effective_date', 'contract_value'],
    [Voucher::class, 'searchVouchers', 'expiry_date', 'current_value'],
    [Warranty::class, 'searchWarranties', 'warranty_end_date', null],
    [ReturnPolicy::class, 'searchReturnPolicies', 'return_deadline', null],
    [BankStatement::class, 'searchBankStatements', 'statement_date', 'closing_balance'],
]);

it('validates ranges and ownership before web and API engine searches', function (string $url, array $filters, string $error) {
    $this->actingAs(User::factory()->create());
    $this->getJson($url.'?'.http_build_query($filters))->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    ['/search', ['date_from' => '2026-02-28', 'date_to' => '2026-02-01'], 'date_to'],
    ['/api/v1/search', ['amount_min' => 20, 'amount_max' => 10], 'amount_max'],
    ['/search', ['date_from' => '2026-02-30'], 'date_from'],
    ['/api/v1/search', ['collection_id' => 99999], 'collection_id'],
]);
