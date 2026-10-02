<?php

use App\Models\File;
use App\Models\Receipt;
use App\Models\Tag;
use App\Models\User;
use App\Services\Search\SearchFacetService;
use App\Services\Search\SearchQueryBuilder;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\MeilisearchEngine;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

beforeEach(function () {
    config(['cache.default' => 'database']);
    $this->actingAs(User::factory()->create());
});

it('uses identical content filters for facets and results with exact type counts', function () {
    $filters = ['type' => 'receipt', 'page' => 1, 'limit' => 20, 'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
        'amount_min' => 10, 'amount_max' => 20, 'category' => 'Books', 'tags' => ['work'], 'collection_id' => '12', 'vendors' => ['Shop']];
    $receipts = Receipt::factory()->count(2)->create(['user_id' => auth()->id()]);
    $hits = $receipts->map(fn ($receipt) => ['id' => $receipt->id])->all();
    $calls = [];
    $client = Mockery::mock(Client::class);
    $index = Mockery::mock(Indexes::class);
    $client->shouldReceive('index')->andReturn($index);
    $index->shouldReceive('rawSearch')->andReturnUsing(function (string $query, array $options) use (&$calls, $hits): array {
        $calls[] = $options;

        return ['hits' => $hits, 'totalHits' => 2, 'estimatedTotalHits' => 99];
    });
    app(EngineManager::class)->extend('facet-test', fn () => new MeilisearchEngine($client));
    config(['scout.driver' => 'facet-test']);
    $page = app(SearchQueryBuilder::class)->searchReceipts('Invoice', $filters);
    $facets = app(SearchFacetService::class)->buildFacets('Invoice', $filters);

    expect($page['total'])->toBe(2)->and($facets['receipts'])->toBe(2)->and($facets['total'])->toBe(16)
        ->and($calls[0]['filter'])->toBe($calls[1]['filter']);
    foreach (array_slice($calls, 1) as $options) {
        expect($options['hitsPerPage'])->toBe(1)->and($options['page'])->toBe(1)
            ->and(array_values(array_unique($options['attributesToRetrieve'])))->toBe(['id'])
            ->and($options['filter'])->toContain('user_id='.auth()->id(), 'tags IN ["work"]', 'collection_ids = "12"', 'vendors IN ["Shop"]');
    }
    app(SearchFacetService::class)->buildFacets('Invoice', array_replace($filters, ['type' => 'document', 'page' => 2, 'limit' => 10]));
    expect($calls)->toHaveCount(9);
});

it('invalidates only the changed tenant on mutation and relationship changes after commit', function () {
    $user = auth()->user();
    $other = User::factory()->create();
    $service = new class extends SearchFacetService
    {
        public int $computations = 0;

        protected function computeFacets(string $query, array $filters, int $userId): array
        {
            $this->computations++;

            return ['total' => Receipt::withoutGlobalScope('user')->where('user_id', $userId)->count()];
        }
    };
    $first = $service->buildFacets('invoice', []);
    $this->actingAs($other);
    $service->buildFacets('invoice', []);
    $this->actingAs($user);
    $file = File::factory()->create(['user_id' => $user->id]);
    $receipt = Receipt::factory()->create(['user_id' => $user->id, 'file_id' => $file->id]);
    expect($service->buildFacets('invoice', [])['total'])->toBe(1)->and($service->computations)->toBe(3);
    $this->actingAs($other);
    $service->buildFacets('invoice', []);
    expect($service->computations)->toBe(3);
    $this->actingAs($user);
    $tag = Tag::factory()->create(['user_id' => $user->id]);
    $file->addTag($tag);
    $service->buildFacets('invoice', []);
    expect($service->computations)->toBe(4);
    $version = Cache::get('search_facets:'.$user->id.':version');
    try {
        $receipt->getConnection()->transaction(function () use ($receipt): void {
            $receipt->update(['total_amount' => 100]);
            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }
    expect(Cache::get('search_facets:'.$user->id.':version'))->toBe($version);
    $receipt->delete();
    expect($service->buildFacets('invoice', [])['total'])->toBe(0);
    $receipt->restore();
    expect($service->buildFacets('invoice', [])['total'])->toBe(1);
});

it('recomputes after database cache clearing and supports owner safe cache locks', function () {
    $service = new class extends SearchFacetService
    {
        public int $computations = 0;

        protected function computeFacets(string $query, array $filters, int $userId): array
        {
            return ['total' => ++$this->computations];
        }
    };
    expect($service->buildFacets('invoice', [])['total'])->toBe(1);
    $version = Cache::get('search_facets:'.auth()->id().':version');
    expect($service->buildFacets('invoice', [])['total'])->toBe(1);
    Cache::flush();
    expect($service->buildFacets('invoice', [])['total'])->toBe(2)
        ->and(Cache::get('search_facets:'.auth()->id().':version'))->not->toBe($version);
    $first = Cache::lock('facet-test', 60);
    $second = Cache::lock('facet-test', 60);
    expect($first->get())->toBeTrue()->and($second->get())->toBeFalse()->and($second->release())->toBeFalse();
    expect($first->release())->toBeTrue()->and($second->get())->toBeTrue();
    $second->release();
});
