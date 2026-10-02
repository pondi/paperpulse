<?php

use App\Http\Controllers\CategoryController;
use App\Models\Category;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->withoutVite();
    Bus::fake();
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->category = Category::create(['user_id' => $this->user->id, 'name' => 'Old', 'slug' => 'old']);
});

it('renames and reassigns receipt labels from owned category IDs', function (): void {
    $receipt = Receipt::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->category->id, 'receipt_category' => 'Wrong']);
    $deleted = Receipt::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->category->id]);
    $deleted->delete();
    $this->category->update(['name' => 'New']);
    expect($receipt->fresh()->receipt_category)->toBe('New')
        ->and($deleted->fresh()->receipt_category)->toBe('New');
    $new = Category::create(['user_id' => $this->user->id, 'name' => 'Other', 'slug' => 'other']);
    $this->post(route('bulk.receipts.categorize'), ['receipt_ids' => [$receipt->id], 'category_id' => $new->id])->assertRedirect();
    expect($receipt->fresh()->category_id)->toBe($new->id)->and($receipt->fresh()->receipt_category)->toBe('Other');
    $foreign = Category::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign', 'slug' => 'foreign']);
    $this->post(route('bulk.receipts.categorize'), ['receipt_ids' => [$receipt->id], 'category_id' => $foreign->id])->assertSessionHasErrors('category_id');
    $receipt->update(['category_id' => null]);
    expect($receipt->fresh()->receipt_category)->toBeNull();
});

it('deletes categories while preserving active and deleted documents as uncategorized', function (): void {
    $receipt = Receipt::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->category->id]);
    $deleted = Receipt::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->category->id]);
    $deleted->delete();
    $document = Document::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->category->id]);
    $invoice = Invoice::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->category->id]);
    $invoice->delete();
    $preference = UserPreference::create(['user_id' => $this->user->id, 'default_category_id' => $this->category->id]);
    $this->delete(route('categories.destroy', $this->category))->assertRedirect();
    foreach ([$receipt, $deleted, $document, $invoice] as $entity) {
        expect($entity->fresh()->category_id)->toBeNull();
    }
    expect($receipt->fresh()->receipt_category)->toBeNull()->and($deleted->fresh()->receipt_category)->toBeNull()
        ->and($preference->fresh()->default_category_id)->toBeNull();
    $this->assertSoftDeleted($deleted);
    $this->assertNotSoftDeleted($receipt);
});

it('computes converted category totals with a bounded query count', function (): void {
    Http::fake(['*' => Http::response("BASE_CUR;QUOTE_CUR;UNIT_MULT;TIME_PERIOD;OBS_VALUE\nEUR;NOK;0;2026-09-25;12\n")]);
    Receipt::factory()->create(['user_id' => $this->user->id, 'category_id' => $this->category->id, 'total_amount' => 10, 'currency' => 'EUR', 'receipt_date' => '2026-09-27']);
    $small = app(CategoryController::class)->index()->toResponse(request())->getOriginalContent()->getData()['page']['props']['categories'];
    expect($small[0]['total_amount'])->toBe(120.0);
    foreach (range(1, 20) as $number) {
        Category::create(['user_id' => $this->user->id, 'name' => 'Category '.$number, 'slug' => 'category-'.$number]);
    }
    $this->user->load('preferences');
    DB::enableQueryLog();
    app(CategoryController::class)->index();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(count($queries))->toBeLessThanOrEqual(4);
});
