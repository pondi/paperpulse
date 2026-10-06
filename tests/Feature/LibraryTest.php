<?php

use App\Models\Collection;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\Tag;
use App\Models\User;
use App\Models\Voucher;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->owner = User::factory()->create();
});

it('shows a paginated library of owned files including unfinished uploads', function (): void {
    File::factory()->count(26)->create(['user_id' => $this->owner->id]);
    File::factory()->create(['user_id' => User::factory()->create()->id]);
    $deleted = File::factory()->create(['user_id' => $this->owner->id]);
    $deleted->delete();

    $this->actingAs($this->owner)->get(route('library.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Library/Index')
            ->has('files.data', 24)->where('files.total', 26)->where('files.last_page', 2)
            ->has('files.data.0.detailsUrl')->has('options.tags')->has('options.collections'));
    $this->get(route('library.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('files.data', 2));
});

it('filters actual entity types even for legacy files and excludes foreign entities', function (): void {
    $invoiceFile = File::factory()->create(['user_id' => $this->owner->id, 'file_type' => 'document']);
    Invoice::factory()->create(['user_id' => $this->owner->id, 'file_id' => $invoiceFile->id]);
    $corrupt = File::factory()->create(['user_id' => $this->owner->id]);
    Invoice::factory()->create(['user_id' => User::factory()->create()->id, 'file_id' => $corrupt->id]);
    $receipt = File::factory()->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->owner)->get(route('library.index', ['type' => 'invoice']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('files.data', 1)->where('files.data.0.id', $invoiceFile->id));
    $this->get(route('library.index', ['type' => 'receipt']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('files.data', 2));
});

it('finds filenames document content and merchant names without leaking another owners text', function (): void {
    $file = File::factory()->create(['user_id' => $this->owner->id, 'fileName' => 'lease.pdf']);
    Document::factory()->create(['user_id' => $this->owner->id, 'file_id' => $file->id, 'content' => 'The annual bicycle allowance']);
    $merchant = Merchant::create(['user_id' => $this->owner->id, 'name' => 'Design Studio']);
    $receiptFile = File::factory()->create(['user_id' => $this->owner->id]);
    $receipt = Receipt::factory()->create(['user_id' => $this->owner->id, 'file_id' => $receiptFile->id, 'merchant_id' => $merchant->id, 'receipt_description' => 'Lunch meeting']);
    $corrupt = File::factory()->create(['user_id' => $this->owner->id]);
    Document::factory()->create(['user_id' => User::factory()->create()->id, 'file_id' => $corrupt->id, 'content' => 'private zebra']);

    $this->actingAs($this->owner);
    foreach (['lease' => $file->id, 'bicycle' => $file->id, 'Design Studio' => $receipt->file_id] as $term => $id) {
        $this->get(route('library.index', ['query' => $term]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('files.data', 1)->where('files.data.0.id', $id));
    }
    $this->get(route('library.index', ['query' => 'private zebra']))->assertInertia(fn (Assert $page) => $page->has('files.data', 0));
});

it('treats percent and underscore as literal search text', function (): void {
    File::factory()->create(['user_id' => $this->owner->id, 'fileName' => '100%_complete.pdf']);
    File::factory()->create(['user_id' => $this->owner->id, 'fileName' => '100xcomplete.pdf']);
    $this->actingAs($this->owner)->get(route('library.index', ['query' => '%_']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('files.data', 1)->where('files.data.0.name', '100%_complete.pdf'));
});

it('combines collection tag status date and name sorting filters', function (): void {
    $collection = Collection::factory()->create(['user_id' => $this->owner->id]);
    $tag = Tag::factory()->create(['user_id' => $this->owner->id]);
    foreach (['Z.pdf', 'A.pdf'] as $name) {
        $file = File::factory()->create(['user_id' => $this->owner->id, 'fileName' => $name, 'status' => 'completed', 'uploaded_at' => '2026-10-03 12:00:00']);
        $file->collections()->attach($collection);
        $file->tags()->attach($tag);
    }
    File::factory()->create(['user_id' => $this->owner->id, 'status' => 'completed']);
    $this->actingAs($this->owner)->get(route('library.index', [
        'collection_id' => $collection->id, 'tag_id' => $tag->id, 'status' => 'completed',
        'date_from' => '2026-10-01', 'date_to' => '2026-10-05', 'sort' => 'name',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('files.data', 2)->where('files.data.0.name', 'A.pdf'));
});

it('keeps smart views current for attention processing unpaid and expiring documents', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 5));
    File::factory()->create(['user_id' => $this->owner->id, 'status' => 'failed']);
    File::factory()->create(['user_id' => $this->owner->id, 'status' => 'needs_review']);
    File::factory()->create(['user_id' => $this->owner->id, 'status' => 'processing']);
    $unpaid = Invoice::factory()->create(['user_id' => $this->owner->id, 'file_id' => File::factory()->create(['user_id' => $this->owner->id])->id, 'invoice_type' => 'invoice', 'payment_status' => 'unpaid']);
    Invoice::factory()->create(['user_id' => $this->owner->id, 'file_id' => File::factory()->create(['user_id' => $this->owner->id])->id, 'payment_status' => 'paid']);
    $contract = Contract::factory()->create(['user_id' => $this->owner->id, 'file_id' => File::factory()->create(['user_id' => $this->owner->id])->id, 'expiry_date' => now()->addDays(10)]);
    Contract::factory()->create(['user_id' => $this->owner->id, 'file_id' => File::factory()->create(['user_id' => $this->owner->id])->id, 'expiry_date' => now()->subDay()]);
    Voucher::factory()->create(['user_id' => $this->owner->id, 'file_id' => File::factory()->create(['user_id' => $this->owner->id])->id, 'expiry_date' => now()->addDays(10), 'is_redeemed' => true]);

    $this->actingAs($this->owner)->get(route('library.index', ['view' => 'needs-review']))
        ->assertInertia(fn (Assert $page) => $page->has('files.data', 2));
    $this->get(route('library.index', ['view' => 'unpaid']))
        ->assertInertia(fn (Assert $page) => $page->has('files.data', 1)->where('files.data.0.id', $unpaid->file_id));
    $this->get(route('library.index', ['view' => 'expiring']))
        ->assertInertia(fn (Assert $page) => $page->has('files.data', 1)->where('files.data.0.id', $contract->file_id));
    $this->get(route('library.index', ['view' => 'processing']))->assertOk();
});

it('uses the users local date for dynamic uploaded ranges', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 31)->setTime(23, 30));
    $this->owner->preferences()->create(['timezone' => 'Europe/Oslo']);
    File::factory()->create(['user_id' => $this->owner->id, 'uploaded_at' => '2026-10-31 23:15:00']);
    File::factory()->create(['user_id' => $this->owner->id, 'uploaded_at' => '2026-10-01 12:00:00']);

    $this->actingAs($this->owner)->get(route('library.index', ['date_range' => 'this_month']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('files.data', 1));
});

it('lists only active shared files and hides private review details and folders', function (): void {
    $sender = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $sender->id]);
    $file = File::factory()->create(['user_id' => $sender->id, 'primary_folder_id' => $collection->id,
        'status' => 'needs_review', 'meta' => ['review' => ['reasoning' => 'private processing detail']]]);
    $share = FileShare::create(['file_id' => $file->id, 'file_type' => 'document', 'shared_by_user_id' => $sender->id,
        'shared_with_user_id' => $this->owner->id, 'permission' => 'view', 'shared_at' => now()]);
    File::factory()->create(['user_id' => $sender->id]);

    $this->actingAs($this->owner)->get(route('library.index', ['view' => 'shared']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('files.data', 1)->where('files.data.0.is_shared', true)
            ->where('files.data.0.folder', null)->missing('files.data.0.review'));
    $share->update(['expires_at' => now()->subMinute()]);
    $this->get(route('library.index', ['view' => 'shared']))->assertInertia(fn (Assert $page) => $page->has('files.data', 0));
});

it('validates filters and tenant owned references', function (array $filters, string $key): void {
    $this->actingAs($this->owner)->getJson(route('library.index', $filters))->assertUnprocessable()->assertJsonValidationErrors($key);
})->with([
    [['type' => 'unknown'], 'type'], [['sort' => 'user_id'], 'sort'],
    [['date_from' => '2026-10-10', 'date_to' => '2026-10-01'], 'date_to'], [['page' => 0], 'page'],
]);

it('rejects foreign collection and tag filters', function (): void {
    $foreign = User::factory()->create();
    $collection = Collection::factory()->create(['user_id' => $foreign->id]);
    $tag = Tag::factory()->create(['user_id' => $foreign->id]);
    $this->actingAs($this->owner)->getJson(route('library.index', ['collection_id' => $collection->id, 'tag_id' => $tag->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['collection_id', 'tag_id']);
});

it('requires authentication for the library', function (): void {
    $this->get(route('library.index'))->assertRedirect(route('login'));
});

it('keeps the current filters when returning from a document workspace', function (): void {
    $file = File::factory()->create(['user_id' => $this->owner->id]);
    $target = '/library?view=recent&display=grid&sort=name';
    $this->actingAs($this->owner)->get($target)->assertInertia(fn (Assert $page) => $page
        ->where('files.data.0.detailsUrl', route('files.show', ['file' => $file->id, 'return_to' => $target])));
    $this->get(route('files.show', ['file' => $file->id, 'return_to' => $target]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('file.back_url', $target)->where('file.back_label', 'Back to Library'));
    $this->get(route('files.show', ['file' => $file->id, 'return_to' => '/search?query=travel']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('file.back_url', '/search?query=travel')->where('file.back_label', 'Back to search'));
    $this->getJson(route('files.show', ['file' => $file->id, 'return_to' => 'https://example.com']))
        ->assertUnprocessable()->assertJsonValidationErrors('return_to');
});

it('exposes extracted library identities and keeps unfinished file names', function (): void {
    $file = File::factory()->create(['user_id' => $this->owner->id, 'fileName' => 'Generic scan.pdf', 'status' => 'completed']);
    $merchant = Merchant::create(['user_id' => $this->owner->id, 'name' => 'Distinct merchant']);
    $receipt = Receipt::factory()->create(['user_id' => $this->owner->id, 'file_id' => $file->id, 'merchant_id' => $merchant->id, 'receipt_date' => '2026-02-10', 'total_amount' => 21.56, 'currency' => 'EUR']);
    ExtractableEntity::create(['user_id' => $this->owner->id, 'file_id' => $file->id, 'entity_type' => 'receipt', 'entity_id' => $receipt->id, 'is_primary' => true, 'extracted_at' => now()]);
    foreach (['processing', 'failed'] as $status) {
        File::factory()->create(['user_id' => $this->owner->id, 'fileName' => $status.'.pdf', 'status' => $status]);
    }
    $this->actingAs($this->owner)->get(route('library.index'))->assertOk()->assertInertia(function (Assert $page) use ($file): void {
        $page->where('files.data', function ($files) use ($file): bool {
            $files = collect($files)->keyBy('id');
            expect($files[$file->id]['identity'])->toMatchArray(['title' => 'Distinct merchant', 'date' => '2026-02-10', 'amount' => '21.56', 'currency' => 'EUR']);
            expect($files->pluck('name')->all())->toContain('Generic scan.pdf', 'processing.pdf', 'failed.pdf');
            expect($files->whereIn('status', ['processing', 'failed'])->pluck('identity')->all())->toBe([null, null]);

            return true;
        });
    });
});
