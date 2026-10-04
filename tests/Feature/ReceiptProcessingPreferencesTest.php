<?php

use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
use App\Models\Category;
use App\Models\File;
use App\Models\Receipt;
use App\Models\Tag;
use App\Models\User;
use App\Services\Factories\ReceiptFactory;
use App\Services\Receipts\Analysis\ReceiptAnalysisRunner;
use App\Services\Receipts\Analysis\UserPreferencesLoader;
use Carbon\Carbon;

it('honors disabled categorization and item extraction in both receipt pipelines', function (string $pipeline) {
    $user = User::factory()->create();
    $category = Category::create(['user_id' => $user->id, 'name' => 'User default', 'slug' => 'default']);
    $user->preferences()->create(['auto_categorize' => false, 'extract_line_items' => false, 'default_category_id' => $category->id, 'currency' => 'EUR']);
    $file = File::factory()->create(['user_id' => $user->id]);
    $data = ['receipt_category' => 'Model guess', 'totals' => ['total_amount' => '10.00'],
        'items' => [['name' => 'Item', 'total_price' => '10.00']]];
    if ($pipeline === 'gemini') {
        $receipt = app(ReceiptFactory::class)->create($data, $file);
    } else {
        $parser = $this->mock(ReceiptParserContract::class, function ($mock) use ($data): void {
            $mock->shouldReceive('extractMerchantData')->andReturn([]);
            $mock->shouldReceive('extractDateTime')->andReturn(Carbon::parse('2024-01-01'));
            $mock->shouldReceive('extractCurrency')->with($data, 'EUR')->andReturn('EUR');
            $mock->shouldReceive('extractItems')->andReturn($data['items']);
            $mock->shouldReceive('extractTotals')->andReturn($data['totals']);
        });
        $validator = $this->mock(ReceiptValidatorContract::class, function ($mock) use ($data): void {
            $mock->shouldReceive('validateParsedData')->andReturn(['valid' => true]);
            $mock->shouldReceive('hasEssentialData')->andReturnTrue();
            $mock->shouldReceive('sanitizeData')->andReturn($data);
        });
        $enricher = $this->mock(ReceiptEnricherContract::class, function ($mock) use ($user): void {
            $mock->shouldReceive('findOrCreateMerchant')->with([], $user->id)->andReturnNull();
            $mock->shouldNotReceive('findUserCategory');
            $mock->shouldNotReceive('categorizeMerchant');
            $mock->shouldReceive('enrichReceiptData')->andReturn([]);
            $mock->shouldReceive('generateEnhancedDescription')->andReturn('Purchase');
        });
        $receipt = (new ReceiptAnalysisRunner($parser, $validator, $enricher))->run(fn () => ['data' => $data], $file->id, $user->id, 'Receipt text');
    }
    expect($receipt->category_id)->toBe($category->id)->and($receipt->receipt_category)->toBe($category->name)
        ->and($receipt->currency)->toBe('EUR')->and($receipt->lineItems()->count())->toBe(0)
        ->and($file->fresh()->meta['processing_preferences']['values']['extract_line_items'])->toBeFalse();
})->with(['gemini', 'legacy']);

it('validates tenant defaults and keeps preferences stable within a processing generation', function () {
    $user = User::factory()->create();
    $otherCategory = Category::create(['user_id' => User::factory()->create()->id, 'name' => 'Private', 'slug' => 'private']);
    $prefs = $user->preferences()->create(['default_category_id' => $otherCategory->id, 'currency' => 'invalid', 'extract_line_items' => false]);
    $file = File::factory()->create(['user_id' => $user->id, 'meta' => ['processing_generation' => 'first']]);
    $loaded = UserPreferencesLoader::load($user->id, $file);
    expect($loaded['default_category_id'])->toBeNull()->and($loaded['default_currency'])->toBe('NOK');
    $prefs->update(['extract_line_items' => true]);
    expect(UserPreferencesLoader::load($user->id, $file)['extract_line_items'])->toBeFalse();
    $file->meta = array_merge($file->meta, ['processing_generation' => 'second']);
    expect(UserPreferencesLoader::load($user->id, $file)['extract_line_items'])->toBeTrue();
});

it('preserves stored user category, notes and tags during receipt replacement and flags total disagreement', function () {
    $file = File::factory()->create();
    $category = Category::create(['user_id' => $file->user_id, 'name' => 'Manual', 'slug' => 'manual']);
    $previous = Receipt::factory()->create(['file_id' => $file->id, 'user_id' => $file->user_id, 'category_id' => $category->id, 'note' => 'My note']);
    $tag = Tag::factory()->create(['user_id' => $file->user_id, 'name' => 'Manual']);
    $file->tags()->attach($tag->id);
    $previous->delete();
    $receipt = app(ReceiptFactory::class)->create(['totals' => ['total_amount' => 100], 'items' => [['name' => 'Item', 'total_price' => 80]]], $file);
    expect($receipt->category_id)->toBe($category->id)->and($receipt->note)->toBe('My note')->and($receipt->tags()->pluck('tags.id')->all())->toBe([$tag->id])
        ->and($receipt->total_amount)->toBe('100.00')->and($file->fresh()->status)->toBe('needs_review')
        ->and($file->fresh()->meta['review']['reason'])->toBe('receipt_totals')
        ->and($receipt->receipt_data['total_reconciliation']['calculated_total'])->toBe('80.00');
});
