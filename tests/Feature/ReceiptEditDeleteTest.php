<?php

use App\Models\Category;
use App\Models\Collection;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('owners can edit and delete receipts through the web routes', function () {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id]);
    $this->actingAs($owner)->patch('/receipts/'.$receipt->id, [
        'receipt_date' => '2026-01-02', 'total_amount' => '25.50', 'currency' => 'NOK', 'note' => 'Edited',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($receipt->fresh()->total_amount)->toBe('25.50')->and($receipt->fresh()->note)->toBe('Edited');
    $this->delete('/receipts/'.$receipt->id)->assertRedirect(route('receipts.index'));
    $this->assertSoftDeleted('receipts', ['id' => $receipt->id]);
    $this->assertSoftDeleted('files', ['id' => $file->id]);
});

test('foreign receipt edits and deletes are rejected without modifying records', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $receipt = Receipt::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($stranger)->patch('/receipts/'.$receipt->id, [
        'receipt_date' => '2026-01-02', 'total_amount' => '25.50', 'currency' => 'NOK',
    ])->assertNotFound();
    $this->delete('/receipts/'.$receipt->id)->assertNotFound();
    $this->assertDatabaseHas('receipts', ['id' => $receipt->id, 'deleted_at' => null]);
});

test('invalid receipt edits return validation errors and retain existing data', function () {
    $owner = User::factory()->create();
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'total_amount' => 10, 'file_id' => File::factory()->create(['user_id' => $owner->id])->id]);
    $this->actingAs($owner)->patch('/receipts/'.$receipt->id, [
        'receipt_date' => 'bad', 'total_amount' => 'bad', 'currency' => 'NOK',
    ])->assertSessionHasErrors(['receipt_date', 'total_amount']);
    expect($receipt->fresh()->total_amount)->toBe('10.00');
});

it('provides managed receipt categories and retains inactive current assignments', function (): void {
    $this->withoutVite();
    $owner = User::factory()->create();
    $garden = Category::create(['user_id' => $owner->id, 'name' => 'Garden & Plants', 'slug' => 'garden-plants', 'is_active' => true]);
    $current = Category::create(['user_id' => $owner->id, 'name' => 'Old category', 'slug' => 'old-category', 'is_active' => false]);
    Category::create(['user_id' => User::factory()->create()->id, 'name' => 'Private', 'slug' => 'private', 'is_active' => true]);
    $file = File::factory()->create(['user_id' => $owner->id]);
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id, 'category_id' => $current->id]);

    $this->actingAs($owner)->get(route('receipts.show', $receipt))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('categories', fn ($categories): bool => $categories->pluck('id')->sort()->values()->all() === [$garden->id, $current->id])
            ->where('receipt.receipt_category', 'Old category'));
    $receipt->update(['category_id' => null]);
    $this->get(route('receipts.show', $receipt))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('categories', 1)->where('receipt.receipt_category', null));
});

it('saves canonical category choices and rejects another owners categories', function (): void {
    $owner = User::factory()->create();
    $category = Category::create(['user_id' => $owner->id, 'name' => 'Garden & Plants', 'slug' => 'garden-plants']);
    $foreign = Category::create(['user_id' => User::factory()->create()->id, 'name' => 'Private', 'slug' => 'private']);
    $receipt = Receipt::factory()->for(File::factory()->for($owner))->create(['user_id' => $owner->id]);
    $payload = ['receipt_date' => '2026-10-05', 'total_amount' => 42, 'currency' => 'NOK'];
    $this->actingAs($owner)->patch(route('receipts.update', $receipt), $payload + ['category_id' => $category->id])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($receipt->fresh()->total_amount)->toBe('42.00');
    expect($receipt->fresh()->category_id)->toBe($category->id)->and($receipt->fresh()->receipt_category)->toBe('Garden & Plants');
    $this->patch(route('receipts.update', $receipt), $payload + ['category_id' => $foreign->id])->assertSessionHasErrors('category_id');
    expect($receipt->fresh()->category_id)->toBe($category->id);
    $this->patch(route('receipts.update', $receipt), $payload + ['category_id' => null])->assertSessionHasNoErrors();
    expect($receipt->fresh()->category_id)->toBeNull()->and($receipt->fresh()->receipt_category)->toBeNull();
});

it('retains merchant context and isolates merchant receipt listings', function (): void {
    $this->withoutVite();
    $owner = User::factory()->create();
    $merchant = Merchant::create(['user_id' => $owner->id, 'name' => 'Eik Senteret']);
    $receipt = Receipt::factory()->for(File::factory()->for($owner))->create(['user_id' => $owner->id, 'merchant_id' => $merchant->id]);
    Receipt::factory()->for(File::factory()->for($owner))->create(['user_id' => $owner->id]);
    $this->actingAs($owner)->get(route('receipts.byMerchant', $merchant))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('merchant.id', $merchant->id)->where('merchant.name', 'Eik Senteret')
            ->has('receipts', 1)->where('receipts.0.id', $receipt->id));
    $this->actingAs(User::factory()->create())->get(route('receipts.byMerchant', $merchant))->assertNotFound();
});

it('saves added removed and empty canonical receipt folder memberships', function (): void {
    $owner = User::factory()->create();
    $file = File::factory()->for($owner)->create();
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id]);
    $old = Collection::factory()->for($owner)->create();
    $new = Collection::factory()->for($owner)->create();
    $file->collections()->attach($old);
    $payload = ['receipt_date' => '2026-10-05', 'total_amount' => 42, 'currency' => 'NOK'];

    $this->actingAs($owner)->patch(route('receipts.update', $receipt), $payload + ['collection_ids' => [$new->id]])->assertRedirect()->assertSessionHasNoErrors();
    expect($file->fresh()->collections->modelKeys())->toBe([$new->id]);
    $this->patch(route('receipts.update', $receipt), $payload)->assertRedirect()->assertSessionHasNoErrors();
    expect($file->fresh()->collections->modelKeys())->toBe([$new->id]);
    $this->patch(route('receipts.update', $receipt), $payload + ['collection_ids' => []])->assertRedirect()->assertSessionHasNoErrors();
    expect($file->fresh()->collections)->toBeEmpty();
});

it('rejects foreign folders without changing receipt data or memberships', function (): void {
    $owner = User::factory()->create();
    $file = File::factory()->for($owner)->create();
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id, 'total_amount' => 10]);
    $folder = Collection::factory()->for($owner)->create();
    $file->collections()->attach($folder);
    $foreign = Collection::factory()->create();
    $this->actingAs($owner)->patch(route('receipts.update', $receipt), [
        'receipt_date' => '2026-10-05', 'total_amount' => 42, 'currency' => 'NOK', 'collection_ids' => [$foreign->id],
    ])->assertSessionHasErrors('collection_ids.0');
    expect($file->fresh()->collections->modelKeys())->toBe([$folder->id])->and($receipt->fresh()->total_amount)->toBe('10.00');
});

it('retains owner folders when a shared editor changes receipt fields', function (): void {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $file = File::factory()->for($owner)->create();
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id]);
    $folder = Collection::factory()->for($owner)->create();
    $file->collections()->attach($folder);
    FileShare::create(['file_id' => $file->id, 'file_type' => 'receipt', 'shared_by_user_id' => $owner->id, 'shared_with_user_id' => $editor->id, 'permission' => 'edit', 'shared_at' => now()]);
    $payload = ['receipt_date' => '2026-10-05', 'total_amount' => 42, 'currency' => 'NOK'];
    $this->actingAs($editor)->patch(route('receipts.update', $receipt), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->patch(route('receipts.update', $receipt), $payload + ['collection_ids' => []])->assertForbidden();
    expect($file->collections()->withoutGlobalScope('user')->pluck('collections.id')->all())->toBe([$folder->id]);
});

it('shows persisted source discounts and current receipt totals', function (bool $encoded): void {
    $this->withoutVite();
    $receipt = Receipt::factory()->create(['total_amount' => '21.56', 'tax_amount' => '0.00', 'currency' => 'EUR',
        'receipt_data' => $encoded ? json_encode(['totals' => ['total_discount' => '1.14']]) : ['totals' => ['total_discount' => '1.14']]]);
    $receipt->lineItems()->createMany([
        ['text' => 'Wine', 'qty' => 1, 'price' => '14.80', 'total' => '14.80'],
        ['text' => 'Wine', 'qty' => 1, 'price' => '7.90', 'total' => '7.90'],
    ]);
    $this->actingAs($receipt->user)->get(route('receipts.show', $receipt))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('receipt.reconciliation.calculated_total', '22.70')
            ->where('receipt.reconciliation.discount_amount', '1.14')
            ->where('receipt.reconciliation.total_amount', '21.56')
            ->where('receipt.reconciliation.needs_review', false));
})->with([false, true]);

it('exposes the same processing and review state on receipt and file views', function (string $status): void {
    $this->withoutVite();
    $file = File::factory()->create(['status' => $status, 'meta' => ['review' => ['reason' => 'receipt_totals']]]);
    $receipt = Receipt::factory()->create(['user_id' => $file->user_id, 'file_id' => $file->id]);
    $this->actingAs($file->user)->get(route('receipts.show', $receipt))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('receipt.file.status', $status)
            ->where('receipt.file.review', $status === 'needs_review' ? ['reason' => 'receipt_totals'] : null));
    $this->get(route('files.show', $file))->assertInertia(fn (AssertableInertia $page) => $page->where('file.status', $status));
})->with(['completed', 'needs_review']);
