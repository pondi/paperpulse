<?php

use App\Models\File;
use App\Models\SavedSearch;
use App\Models\Tag;
use App\Models\User;
use App\Services\SearchService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->owner = User::factory()->create();
});

it('saves personal filters and opens them as a current library query', function (): void {
    $filters = ['type' => 'receipt', 'date_range' => 'this_month', 'display' => 'grid', 'sort' => 'name'];
    $this->actingAs($this->owner)->post(route('saved-searches.store'), [
        'name' => ' Monthly receipts ', 'scope' => 'library', 'filters' => $filters, 'is_pinned' => true,
    ])->assertRedirect();
    $saved = SavedSearch::firstOrFail();
    expect($saved->name)->toBe('Monthly receipts')->and($saved->user_id)->toBe($this->owner->id)
        ->and($saved->filters)->toEqual($filters)->and($saved->is_pinned)->toBeTrue();
    $this->get(route('saved-searches.show', $saved))->assertRedirect(route('library.index', [...$saved->filters, 'saved_search' => $saved->id]));
    $this->get(route('library.index', [...$filters, 'saved_search' => $saved->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('activeView.name', 'Monthly receipts')->where('filters.display', 'grid'));
});

it('recalculates dynamic view dates and includes documents added after saving', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 5));
    $saved = SavedSearch::factory()->create(['user_id' => $this->owner->id, 'filters' => ['date_range' => 'this_month']]);
    File::factory()->create(['user_id' => $this->owner->id, 'uploaded_at' => '2026-10-03']);
    File::factory()->create(['user_id' => $this->owner->id, 'uploaded_at' => '2026-11-03']);
    $this->actingAs($this->owner)->get(route('library.index', [...$saved->filters, 'saved_search' => $saved->id]))
        ->assertInertia(fn (Assert $page) => $page->has('files.data', 1));
    $this->travelTo(now()->setDate(2026, 11, 5));
    File::factory()->create(['user_id' => $this->owner->id, 'uploaded_at' => '2026-11-04']);
    $this->get(route('library.index', [...$saved->filters, 'saved_search' => $saved->id]))
        ->assertInertia(fn (Assert $page) => $page->has('files.data', 2));
});

it('persists full text searches and restores their initial filters', function (): void {
    $this->actingAs($this->owner)->post(route('saved-searches.store'), [
        'name' => 'Travel expenses', 'scope' => 'search',
        'filters' => ['query' => 'flight', 'type' => 'receipt', 'amount_min' => 100, 'amount_max' => 500, 'tags' => ['Business']],
    ])->assertRedirect();
    $saved = SavedSearch::firstOrFail();
    $this->get(route('saved-searches.show', $saved))->assertRedirect(route('search', [...$saved->filters, 'saved_search' => $saved->id]));
    $this->mock(SearchService::class, function ($mock): void {
        $mock->shouldReceive('search')->once()->andReturn([
            'results' => [], 'facets' => [], 'search_status' => 'available',
            'pagination' => ['page' => 1, 'last_page' => 0, 'total' => 0],
        ]);
    });
    $this->get(route('search', [...$saved->filters, 'saved_search' => $saved->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('query', 'flight')->where('initialFilters.type', 'receipt')
            ->where('initialFilters.amount_min', '100')->where('activeView.name', 'Travel expenses'));
});

it('supports rename pin update and deletion without changing documents', function (): void {
    $saved = SavedSearch::factory()->create(['user_id' => $this->owner->id]);
    $file = File::factory()->create(['user_id' => $this->owner->id]);
    $this->actingAs($this->owner)->patch(route('saved-searches.update', $saved), ['name' => 'New name', 'is_pinned' => false])->assertRedirect();
    expect($saved->fresh()->name)->toBe('New name')->and($saved->fresh()->is_pinned)->toBeFalse();
    $this->patch(route('saved-searches.update', $saved), ['scope' => 'library', 'filters' => ['status' => 'completed']])->assertRedirect();
    expect($saved->fresh()->filters)->toBe(['status' => 'completed']);
    $this->delete(route('saved-searches.destroy', $saved))->assertRedirect(route('saved-searches.index'));
    expect(SavedSearch::find($saved->id))->toBeNull()->and($file->fresh())->not->toBeNull();
});

it('prevents access to another users saved views', function (): void {
    $foreign = SavedSearch::factory()->create();
    $this->actingAs($this->owner)->get(route('saved-searches.index'))->assertInertia(fn (Assert $page) => $page->has('views', 0));
    $this->get(route('saved-searches.show', $foreign))->assertNotFound();
    $this->patch(route('saved-searches.update', $foreign), ['name' => 'Stolen'])->assertNotFound();
    $this->delete(route('saved-searches.destroy', $foreign))->assertNotFound();
    $this->get(route('library.index', ['saved_search' => $foreign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('activeView', null)->has('navigation.saved_views', 0));
});

it('rejects unsupported or invalid saved filters', function (array $data, string $error): void {
    $this->actingAs($this->owner)->postJson(route('saved-searches.store'), [
        'name' => 'My view', 'scope' => 'library', 'filters' => ['type' => 'receipt'], ...$data,
    ])->assertUnprocessable()->assertJsonValidationErrors($error);
    expect(SavedSearch::count())->toBe(0);
})->with([
    [['name' => '  '], 'name'],
    [['scope' => 'external'], 'scope'],
    [['filters' => ['user_id' => 20]], 'filters'],
    [['filters' => ['page' => 2]], 'filters'],
    [['filters' => ['type' => 'unknown']], 'filters.type'],
    [['filters' => ['date_from' => '2026-10-10', 'date_to' => '2026-10-01']], 'filters.date_to'],
    [['scope' => 'search', 'filters' => ['amount_min' => 100, 'amount_max' => 10]], 'filters.amount_max'],
]);

it('rejects foreign references in saved views', function (): void {
    $tag = Tag::factory()->create();
    $this->actingAs($this->owner)->postJson(route('saved-searches.store'), [
        'name' => 'Wrong tag', 'scope' => 'library', 'filters' => ['tag_id' => $tag->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('filters.tag_id');
});

it('shares only the current users pinned view names in navigation', function (): void {
    SavedSearch::factory()->create(['user_id' => $this->owner->id, 'name' => 'Pinned']);
    SavedSearch::factory()->create(['user_id' => $this->owner->id, 'is_pinned' => false]);
    SavedSearch::factory()->create(['name' => 'Other owner']);
    $this->actingAs($this->owner)->get(route('library.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('navigation.saved_views', 1)->where('navigation.saved_views.0.name', 'Pinned'));
});
