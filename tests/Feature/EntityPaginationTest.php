<?php

use App\Models\Category;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Voucher;
use Inertia\Testing\AssertableInertia as Assert;

it('bounds entity lists and preserves filters across pages', function (string $model, string $route, string $prop, string $field): void {
    $user = User::factory()->create();
    $matches = $model::factory()->count(23)->create(['user_id' => $user->id, $field => 'PaginationMatch']);
    $model::factory()->create(['user_id' => $user->id, $field => 'Excluded']);
    $model::factory()->create([$field => 'PaginationMatch']);

    $query = ['search' => 'PaginationMatch', 'per_page' => 10];
    $first = $this->actingAs($user)->get(route($route, $query))->assertOk();
    $first->assertInertia(fn (Assert $page) => $page
        ->has($prop, 10)
        ->where('pagination.total', 23)
        ->where('filters.search', 'PaginationMatch')
    );
    $second = $this->get(route($route, $query + ['page' => 2]))->assertOk();
    $second->assertInertia(fn (Assert $page) => $page->has($prop, 10)->where('pagination.total', 23));

    $firstIds = collect($first->original->getData()['page']['props'][$prop])->pluck('id');
    $secondIds = collect($second->original->getData()['page']['props'][$prop])->pluck('id');
    expect($firstIds->intersect($secondIds))->toBeEmpty()
        ->and($firstIds->merge($secondIds)->diff($matches->pluck('id')))->toBeEmpty();
    $links = $first->original->getData()['page']['props']['pagination']['links'];
    expect(collect($links)->pluck('url')->filter()->every(fn (string $url): bool => str_contains($url, 'search=PaginationMatch')))->toBeTrue();
})->with([
    [Invoice::class, 'invoices.index', 'invoices', 'invoice_number'],
    [Contract::class, 'contracts.index', 'contracts', 'contract_title'],
    [Voucher::class, 'vouchers.index', 'vouchers', 'code'],
    [Receipt::class, 'receipts.index', 'receipts', 'receipt_description'],
]);

it('rejects invalid list sizes and page numbers', function (string $route): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route($route, ['per_page' => 1000, 'page' => -1, 'sort_direction' => 'invalid']))
        ->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'page', 'sort_direction']);
})->with(['invoices.index', 'contracts.index', 'vouchers.index', 'receipts.index']);

it('searches receipt notes on the owned source file', function (): void {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $receipt = Receipt::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'note' => 'SourceNoteMatch']);
    Receipt::factory()->create(['user_id' => $user->id]);
    $foreignFile = File::factory()->create();
    Receipt::factory()->create(['user_id' => $foreignFile->user_id, 'file_id' => $foreignFile->id, 'note' => 'SourceNoteMatch']);

    $this->actingAs($user)->get(route('receipts.index', ['search' => 'sourcenotematch']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('receipts', 1)->where('receipts.0.id', $receipt->id)->where('pagination.total', 1));
});

it('rejects unsupported sort and type filters', function (string $route): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route($route, ['sort' => 'user_id', 'type' => 'unsupported']))
        ->assertUnprocessable()->assertJsonValidationErrors(['sort', 'type']);
})->with(['invoices.index', 'contracts.index', 'vouchers.index']);

it('applies invoice status and type filters before pagination', function (): void {
    $user = User::factory()->create();
    Invoice::factory()->count(3)->create(['user_id' => $user->id, 'payment_status' => 'paid', 'invoice_type' => 'credit_note']);
    Invoice::factory()->create(['user_id' => $user->id, 'payment_status' => 'unpaid', 'invoice_type' => 'credit_note']);
    Invoice::factory()->create(['user_id' => $user->id, 'payment_status' => 'paid', 'invoice_type' => 'invoice']);

    $this->actingAs($user)->get(route('invoices.index', ['paymentStatus' => 'paid', 'type' => 'credit_note', 'per_page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('invoices', 2)->where('pagination.total', 3));
});

it('applies contract status and type filters before pagination', function (): void {
    $user = User::factory()->create();
    Contract::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'active', 'contract_type' => 'service']);
    Contract::factory()->create(['user_id' => $user->id, 'status' => 'draft', 'contract_type' => 'service']);
    Contract::factory()->create(['user_id' => $user->id, 'status' => 'active', 'contract_type' => 'rental']);

    $this->actingAs($user)->get(route('contracts.index', ['status' => 'active', 'type' => 'service', 'per_page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('contracts', 2)->where('pagination.total', 3));
});

it('filters vouchers using the user calendar date and redemption state', function (): void {
    $user = User::factory()->create();
    Voucher::factory()->count(3)->create(['user_id' => $user->id, 'voucher_type' => 'coupon', 'expiry_date' => $user->currentDate(), 'is_redeemed' => false]);
    Voucher::factory()->create(['user_id' => $user->id, 'voucher_type' => 'coupon', 'expiry_date' => $user->currentDate()->subDay(), 'is_redeemed' => false]);
    Voucher::factory()->create(['user_id' => $user->id, 'voucher_type' => 'coupon', 'expiry_date' => null, 'is_redeemed' => true]);

    $this->actingAs($user)->get(route('vouchers.index', ['status' => 'active', 'type' => 'coupon', 'per_page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('vouchers', 2)->where('pagination.total', 3));
});

it('keeps legacy merchant receipt links paginated with categories and scoped filters', function (): void {
    $user = User::factory()->create();
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Scoped merchant']);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Travel', 'slug' => 'travel']);
    Receipt::factory()->count(21)->for($user)->create(['merchant_id' => $merchant->id, 'category_id' => $category->id, 'receipt_description' => 'ScopeMatch']);
    Receipt::factory()->for($user)->create(['receipt_description' => 'ScopeMatch']);
    $this->actingAs($user)->get(route('receipts.byMerchant', ['merchant' => $merchant->id, 'search' => 'ScopeMatch', 'category_id' => $category->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('receipts', 20)->where('pagination.total', 21)
        ->where('merchant.id', $merchant->id)->where('category.id', $category->id)->where('categories.0.id', $category->id)
        ->where('pagination.links.3.url', fn ($url) => str_contains($url, 'search=ScopeMatch') && str_contains($url, 'category_id='.$category->id) && str_contains($url, 'page=2')));
    $this->get(route('receipts.index', ['merchant_id' => $merchant->id, 'search' => 'missing']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('receipts', 0)->where('merchant.name', 'Scoped merchant'));
    $this->getJson(route('receipts.byMerchant', ['merchant' => $merchant->id, 'per_page' => 101]))->assertUnprocessable();
});

it('retains document and shared document search scopes across pagination', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $category = Category::create(['user_id' => $owner->id, 'name' => 'Agreements', 'slug' => 'agreements']);
    foreach (range(1, 21) as $number) {
        $file = File::factory()->for($owner)->create(['file_type' => 'document', 'status' => 'completed', 'fileName' => sprintf('Scoped %02d.pdf', $number)]);
        $document = Document::factory()->for($owner)->create(['file_id' => $file->id, 'title' => 'Scoped document '.$number, 'category_id' => $category->id]);
        ExtractableEntity::factory()->create(['file_id' => $file->id, 'user_id' => $owner->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true]);
        FileShare::create(['file_id' => $file->id, 'file_type' => 'document', 'shared_by_user_id' => $owner->id, 'shared_with_user_id' => $viewer->id, 'permission' => 'view', 'shared_at' => now()]);
    }
    $this->actingAs($owner)->get(route('documents.index', ['search' => 'Scoped', 'category' => $category->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('documents.data', 20)->where('documents.total', 21)
            ->where('documents.next_page_url', fn ($url) => str_contains($url, 'search=Scoped') && str_contains($url, 'category='.$category->id)));
    $this->get(route('documents.index', ['search' => 'Scoped', 'category' => $category->id, 'page' => 2]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.total', 21));
    $this->actingAs($viewer)->get(route('documents.shared', ['search' => 'Scoped']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('documents.total', 21)->where('documents.next_page_url', fn ($url) => str_contains($url, 'search=Scoped')));
    $this->get(route('documents.shared', ['search' => 'missing']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('documents.data', 0));
});
