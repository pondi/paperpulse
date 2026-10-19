<?php

use App\Models\ArchiveExport;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Contract;
use App\Models\Document;
use App\Models\DuplicateFlag;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\JobHistory;
use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\OrganizationRun;
use App\Models\PulseDavFile;
use App\Models\Receipt;
use App\Models\Tag;
use App\Models\UserPreference;
use App\Models\Vendor;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\Files\StoragePathBuilder;
use Dompdf\Dompdf;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Smalot\PdfParser\Parser;

beforeEach(function (): void {
    $this->browse(fn (Browser $browser) => $browser->resize(1440, 1000));
});

it('explains processing usage limits on failed file reports', function (): void {
    $user = $this->createUser();
    $file = File::factory()->create(['user_id' => $user->id, 'status' => 'failed', 'meta' => [
        'gemini_error' => ['category' => 'usage_budget_exceeded', 'retryable' => false, 'timestamp' => now()->toISOString()],
    ]]);
    $this->browse(function (Browser $browser) use ($user, $file): void {
        $browser->loginAs($user)->visit('/files/'.$file->id.'/extraction-report')
            ->waitForText('The processing usage limit was reached.')
            ->assertSee('This file exceeded its processing allowance. Contact support before retrying.')
            ->assertSee('Failure diagnostics')->click('@failure-diagnostics')->assertSee('Failed stage')->assertSee((string) $file->id)
            ->assertDontSee('The failure cause was not recorded.');
    });
});

it('distinguishes recommendation generation failures pending work and successful empty reviews', function (string $status, string $message): void {
    $user = $this->createUser();
    $run = OrganizationRun::create(['user_id' => $user->id, 'active_user_id' => $status === 'completed' ? null : $user->id,
        'input_revision' => 0, 'input_fingerprint' => str_repeat('a', 64), 'status' => $status, 'attempts' => 1,
        'started_at' => $status === 'queued' ? null : now(), 'error' => $status === 'failed' ? 'Recommendations could not be generated. Retry this run or dismiss it.' : null]);
    if ($status === 'awaiting_decisions') {
        $folder = Collection::factory()->for($user)->create(['name' => 'Building']);
        $run->recommendations()->create(['user_id' => $user->id, 'operation' => ['type' => 'rename', 'folder_id' => $folder->id, 'parent_id' => null, 'file_ids' => [], 'name' => 'Home'],
            'before_state' => ['files' => [], 'folders' => [$folder->id => ['members_count' => 0, 'parent_id' => null]]],
            'signature' => str_repeat('b', 64), 'confidence' => 0.95, 'reason' => 'Rename Building to Home']);
    }
    $this->browse(function (Browser $browser) use ($user, $status, $message): void {
        $browser->loginAs($user)->visit('/collections/recommendations')->waitForText($message)->assertSee('Archive folder review')->assertSee('Review status');
        if ($status === 'awaiting_decisions') {
            $browser->assertSee('1 decision remaining');
        }
        foreach ([320, 390] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
        }
        if ($status === 'failed') {
            $browser->assertDontSee('No folder changes to review')->assertSee('Failed')->assertSee('Retry')->assertSee('Dismiss failed run');
        } else {
            $browser->assertDontSee('Folder recommendation generation failed');
        }
    });
})->with([
    ['queued', 'Your recommendations are being prepared.'],
    ['running', 'Your recommendations are being prepared.'],
    ['failed', 'Folder recommendation generation failed'],
    ['completed', 'No folder changes to review.'],
    ['awaiting_decisions', 'Rename Building to Home'],
]);

it('keeps scanner error exits visible and provides file upload recovery', function (string $failure, string $message): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user, $failure, $message): void {
        $browser->loginAs($user)->visit('/dashboard');
        $browser->script("window.cv = { getBuildInformation: () => 'ready' }; navigator.mediaDevices.getUserMedia = () => Promise.reject(new DOMException('Camera unavailable', '".$failure."'));");
        $browser->click('@add-document')->clickLink('Scan a document')->waitForText($message)
            ->assertSee('Upload a file')->assertSee('Close scanner')
            ->assertScript("(() => { const el = document.querySelector('a[aria-label=\"Close scanner\"]'); const r = el.getBoundingClientRect(); return el.contains(document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)); })()", true)
            ->clickLink('Upload a file')->waitForLocation('/documents/upload');
        $browser->back()->waitForText($message);
        $browser->script("document.querySelector('a[aria-label=\"Close scanner\"]').focus()");
        $browser->keys('a[aria-label="Close scanner"]', WebDriverKeys::ENTER)->waitForLocation('/dashboard');
    });
})->with([
    ['NotAllowedError', 'Camera permission was denied'],
    ['NotFoundError', 'No camera was found'],
]);

it('keeps scanner capture controls accessible while camera permission is pending', function (): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/dashboard');
        $browser->script("window.cv = { getBuildInformation: () => 'ready' }; navigator.mediaDevices.getUserMedia = () => new Promise(() => {});");
        $browser->click('@add-document')->clickLink('Scan a document')->waitFor('[role="status"]')
            ->assertSee('Starting camera…')->assertDisabled('button[aria-label="Capture scan"]')
            ->assertPresent('[role="group"][aria-label="Scan type"] button[aria-pressed="true"]')
            ->assertPresent('button[aria-label="Add scan details"]');
        foreach ([390, 320] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
                ->assertScript("document.querySelector('button[aria-label=\"Capture scan\"]').getBoundingClientRect().bottom < window.innerHeight", true);
        }
    });
});

it('names row selection and search result links with distinct record identities', function (): void {
    $user = $this->createUser();
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Same store']);
    Receipt::factory()->count(2)->for(File::factory()->for($user))->create(['user_id' => $user->id, 'merchant_id' => $merchant->id]);
    $file = File::factory()->for($user)->create(['file_type' => 'document', 'status' => 'completed']);
    $document = Document::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'title' => 'Named source']);
    ExtractableEntity::create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true, 'extracted_at' => now()]);
    $this->browse(function (Browser $browser) use ($user, $file): void {
        $browser->loginAs($user)->visit('/receipts')->waitFor('tbody input[type="checkbox"]')
            ->assertScript("new Set(Array.from(document.querySelectorAll('tbody input[type=checkbox]')).map(el => el.getAttribute('aria-label'))).size", 2)
            ->visit('/documents')->waitFor('input[aria-label="Select Named source (file #'.$file->id.')"]')
            ->click('button[aria-label="List view"]')->waitFor('table')
            ->assertPresent('thead input[aria-label="Select all documents on this page"]')
            ->check('thead input[type="checkbox"]')->assertChecked('tbody input[type="checkbox"]')
            ->visit('/search?query=Same%20store&type=receipt')->waitFor('input[aria-label^="Select receipt"]')
            ->assertPresent('input[aria-label="Select all search results on this page"]')
            ->assertScript("Array.from(document.querySelectorAll('a[target=_blank]')).filter(a => a.title === 'Open in new tab').every(a => a.getAttribute('aria-label')?.includes('Same store'))", true);
    });
});

it('opens useful vendor details through the item link with working back navigation', function (): void {
    $user = $this->createUser();
    $vendor = Vendor::create(['user_id' => $user->id, 'name' => 'DigitalOcean', 'description' => 'Cloud hosting', 'contact_email' => 'support@example.test']);
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id, 'currency' => 'NOK']);
    LineItem::create(['receipt_id' => $receipt->id, 'vendor_id' => $vendor->id, 'text' => 'Cloud subscription', 'qty' => 1, 'price' => 100]);
    $this->browse(function (Browser $browser) use ($user, $vendor): void {
        $browser->loginAs($user)->visit('/vendors')->waitForText('DigitalOcean')
            ->assertSee('View 1 purchased item')
            ->clickLink('View 1 purchased item')
            ->waitForText('Vendor details')->assertSee('Cloud hosting')->assertSee('support@example.test')
            ->assertSee('Cloud subscription')->clickLink('Back to vendors')->waitForLocation('/vendors')
            ->back()->waitForLocation('/vendors/'.$vendor->id)->assertSee('Cloud subscription')
            ->visit('/vendors/'.$vendor->id)->waitForText('Purchased items (1)');
    });
});

it('connects tags and merchants to their matching records while keeping vendor drilldowns distinct', function (): void {
    $user = $this->createUser();
    $file = File::factory()->for($user)->create(['fileName' => 'Tagged planning document.pdf']);
    $tag = Tag::factory()->for($user)->create(['name' => 'Planning']);
    $file->addTag($tag);
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Northstar Market']);
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id, 'merchant_id' => $merchant->id]);
    $vendor = Vendor::create(['user_id' => $user->id, 'name' => 'Northstar Foods']);
    LineItem::create(['receipt_id' => $receipt->id, 'vendor_id' => $vendor->id, 'text' => 'Coffee', 'qty' => 1, 'price' => 40]);

    $this->browse(function (Browser $browser) use ($user, $tag, $merchant, $vendor): void {
        $browser->loginAs($user)->visit('/tags')->waitForText('Planning');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
            $browser->assertPresent('a[href*="tag_id='.$tag->id.'"]');
        }
        $browser->resize(1440, 1000)
            ->assertSee('Browse 1 file')->clickLink('Browse 1 file')
            ->waitUntil("location.pathname === '/library' && new URLSearchParams(location.search).get('tag_id') === '{$tag->id}'")
            ->assertSee('Tagged planning document.pdf')
            ->visit('/merchants')->waitForText('Northstar Market')->assertSee('View 1 receipt');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
            $browser->assertScript("(() => { const link = document.querySelector('a[href$=\"/receipts/merchant/{$merchant->id}\"]'); const rect = link.getBoundingClientRect(); return rect.width >= 44 && rect.height >= 44 && rect.right <= innerWidth; })()", true);
        }
        $browser->resize(1440, 1000)
            ->clickLink('View 1 receipt')->waitForLocation('/receipts/merchant/'.$merchant->id)
            ->assertSee('Northstar Market')
            ->visit('/vendors')->waitForText('Northstar Foods')->assertSee('View 1 purchased item');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
            $browser->assertScript("(() => { const link = document.querySelector('a[href$=\"/vendors/{$vendor->id}\"]'); const rect = link.getBoundingClientRect(); return rect.width >= 44 && rect.height >= 44 && rect.right <= innerWidth; })()", true);
        }
        $browser->resize(1440, 1000)
            ->clickLink('View 1 purchased item')->waitForLocation('/vendors/'.$vendor->id)
            ->assertSee('Purchased items (1)')->assertSee('Coffee');
    });
});

it('browses categorized receipts and explains filtered empty document and receipt lists', function (): void {
    $user = $this->createUser();
    $category = Category::create(['user_id' => $user->id, 'name' => 'Garden & Plants', 'slug' => 'garden-plants']);
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Eik Senteret']);
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id, 'category_id' => $category->id, 'merchant_id' => $merchant->id]);
    $this->browse(function (Browser $browser) use ($user, $category): void {
        $browser->loginAs($user)->visit('/documents/categories')->waitForText('Garden & Plants')
            ->clickLink('View receipts (1) →')->waitForText('Receipts · Garden & Plants')->assertSee('Eik Senteret')
            ->clickLink('All Receipts')->waitUntil("location.search === ''")
            ->visit('/documents?category='.$category->id)->waitForText('No documents in Garden & Plants')
            ->assertDontSee('Upload your first document')->clickLink('All documents')->waitUntil("location.search === ''");
    });
    $receipt->delete();
    $this->browse(function (Browser $browser) use ($category): void {
        $browser->visit('/receipts?category_id='.$category->id)->waitForText('No receipts in Garden & Plants')
            ->assertDontSee('Upload Your First Receipts')->assertPresent('a[href$="/receipts"]');
    });
});

it('shows actual document category counts including zero and singular usage', function (): void {
    $user = $this->createUser();
    foreach ([0, 1, 2] as $count) {
        $category = Category::create(['user_id' => $user->id, 'name' => 'Category '.$count, 'slug' => 'category-'.$count]);
        for ($number = 0; $number < $count; $number++) {
            $file = File::factory()->for($user)->create(['file_type' => 'document', 'status' => 'completed']);
            $document = Document::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'category_id' => $category->id]);
            ExtractableEntity::create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true, 'extracted_at' => now()]);
        }
    }

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/documents/categories')->waitForText('Category 0')
            ->assertSee('0 documents')->assertSee('1 document')->assertSee('2 documents');
        $browser->click('div.group:has(h3) a')->waitForLocation('/documents')->waitForText('No documents in Category 0');
    });
});

it('keeps failed file retry actions visible on narrow activity pages', function (): void {
    $user = $this->createUser();
    File::factory()->create(['user_id' => $user->id, 'status' => 'failed', 'fileName' => str_repeat('Long filename ', 15).'.pdf']);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/files-processing?status=failed')->waitFor('button.workspace-primary')->waitForText('Show details')
            ->press('Show details')->waitForText('Extraction report');
        foreach ([320, 390] as $width) {
            $browser->resize($width, 844)->assertPresent('button.workspace-primary');
            $browser->assertScript("Array.from(document.querySelectorAll('button')).filter(el => /Retry Processing/.test(el.textContent)).every(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; })", true);
        }
    });
});

it('renders document metadata and navigation with populated and nullable dates', function (): void {
    $user = $this->createUser();
    UserPreference::create(['user_id' => $user->id, 'date_format' => 'Y-m-d', 'timezone' => 'UTC']);

    $this->browse(function (Browser $browser) use ($user): void {
        foreach (['2026-10-05', null] as $date) {
            $document = Document::factory()->for(File::factory()->for($user))->create([
                'user_id' => $user->id,
                'title' => 'Electrical plan',
                'document_date' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
            $browser->loginAs($user)->visit('/documents/'.$document->id)
                ->waitForText('Electrical plan')->waitForText('Document information')->assertPresent('@workspace-back');
            $browser->assertScript("Array.from(document.querySelectorAll('dt')).find(el => el.textContent === 'Document date').nextElementSibling.textContent.trim()", $date ?? 'Not available')
                ->click('@workspace-back')->waitForLocation('/library')->assertPresent('@library-query');
        }
    });
});

it('keeps nested folder actions and breadcrumbs within narrow viewports', function (): void {
    $user = $this->createUser();
    $parent = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Building with a long folder name']);
    $child = Collection::factory()->create(['user_id' => $user->id, 'parent_id' => $parent->id, 'name' => 'Contracts with a long folder name']);

    $this->browse(function (Browser $browser) use ($user, $child): void {
        $browser->loginAs($user)->visit('/collections/'.$child->id)->waitForText($child->name);

        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844);
            $browser->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
            $browser->press('Details')->waitFor('[data-folder-details]')->assertSee('Edit Collection')->assertSee('Review recommendations')->press('Close')->waitUntilMissing('[role="dialog"]');
            $browser->assertScript("Array.from(document.querySelectorAll('header a, header button, nav[aria-label=Breadcrumb] a')).every(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= window.innerWidth; })", true);
        }
    });
});

it('returns from a nested folder to its parent while keeping all collections reachable', function (): void {
    $user = $this->createUser();
    $parent = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Building']);
    $child = Collection::factory()->create(['user_id' => $user->id, 'parent_id' => $parent->id, 'name' => 'Contracts']);

    $this->browse(function (Browser $browser) use ($user, $parent, $child): void {
        $browser->loginAs($user)->visit('/collections/'.$child->id)->waitForText('Back to Building')
            ->clickLink('Back to Building')->waitForLocation('/collections/'.$parent->id)
            ->assertDontSee('Back to Building')->clickLink('All Collections')->waitForLocation('/collections');
    });
});

it('names management actions with their folder or category target', function (): void {
    $user = $this->createUser();
    Collection::factory()->create(['user_id' => $user->id, 'name' => 'Named folder']);
    Collection::factory()->create(['user_id' => $user->id, 'name' => 'Archived folder', 'is_archived' => true]);
    Category::create(['user_id' => $user->id, 'name' => 'Named category', 'slug' => 'named-category', 'is_active' => true]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/collections')->waitForText('Named folder')
            ->click('button[aria-label="Actions for Named folder"]')->waitFor('[role="menu"]')
            ->assertSeeIn('[role="menu"]', 'Edit folder')->assertSeeIn('[role="menu"]', 'Archive folder')->assertSeeIn('[role="menu"]', 'Delete folder')
            ->visit('/collections?archived=1')->waitForText('Archived folder')
            ->click('button[aria-label="Actions for Archived folder"]')->waitFor('[role="menu"]')->assertSeeIn('[role="menu"]', 'Restore folder')
            ->visit('/categories')->waitForText('Named category')
            ->assertPresent('button[aria-label="Edit Category: Named category"]')
            ->assertPresent('button[aria-label="Delete Category: Named category"]')
            ->visit('/documents/categories')->waitForText('Named category')
            ->assertPresent('button[aria-label="Edit category: Named category"]')
            ->assertPresent('button[aria-label="Delete category: Named category"]');
    });
});

it('gives mobile folder controls separated touch targets aligned with their icons', function (): void {
    $user = $this->createUser();
    Collection::factory()->create(['user_id' => $user->id, 'name' => 'Long folder name for mobile management']);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/collections')->waitForText('Long folder name for mobile management');

        foreach ([320, 390] as $width) {
            $browser->resize($width, 844)->click('button[aria-label="Actions for Long folder name for mobile management"]')->waitFor('[role="menu"]');
            $browser->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
            $browser->assertScript(<<<'JS'
                (() => {
                    const trigger = document.querySelector('button[aria-label="Actions for Long folder name for mobile management"]');
                    const rect = trigger.getBoundingClientRect();
                    const icon = trigger.querySelector('svg').getBoundingClientRect();
                    const controls = Array.from(document.querySelectorAll('[role="menu"] button'));
                    return rect.width >= 44 && rect.height >= 44 && Math.abs((rect.left + rect.right) / 2 - (icon.left + icon.right) / 2) < 1
                        && controls.length === 3 && controls.every((button, index) => {
                        const target = button.getBoundingClientRect();
                        return target.width >= 44 && target.height >= 44 && target.right <= window.innerWidth
                            && target.left >= 0 && (!index || controls[index - 1].getBoundingClientRect().bottom <= target.top);
                    });
                })()
                JS, true);
            $browser->keys('[role="menu"]', WebDriverKeys::ESCAPE)->waitUntilMissing('[role="menu"]');
        }
    });
});

it('keeps long invoice identities dates and amounts readable in mobile search cards', function (): void {
    $user = $this->createUser();
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'DigitalOcean']);
    Invoice::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'from_name' => 'DigitalOcean with a longer merchant identity',
        'invoice_date' => '2026-10-05',
        'total_amount' => 1234.56,
        'currency' => 'USD',
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/search?query=DigitalOcean&type=invoice')->waitForText('$1,234.56');

        foreach ([320, 390, 1440] as $width) {
            $browser->resize($width, 844)->assertSee('DigitalOcean with a longer merchant identity');
            $browser->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
            $browser->assertScript(<<<'JS'
                (() => {
                    const card = document.querySelector('[aria-label="Search results"] article');
                    const title = card.querySelector('h2 button span');
                    const date = card.querySelector('h2').previousElementSibling.getBoundingClientRect();
                    const amount = card.querySelector('p.tabular-nums').getBoundingClientRect();
                    const style = getComputedStyle(title);
                    return style.textOverflow !== 'ellipsis' && (date.bottom <= amount.top || date.right <= amount.left);
                })()
                JS, true);
        }
    });
});

it('keeps distinct visible associated labels on populated search filters', function (): void {
    $user = $this->createUser();
    Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id, 'total_amount' => 42, 'currency' => 'NOK']);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(1440, 900)->loginAs($user)->visit('/search?type=receipt')->waitFor('#search-sort');
        $labels = [
            'search-date-from' => 'From date',
            'search-date-to' => 'To date',
            'search-amount-min' => 'Minimum amount',
            'search-amount-max' => 'Maximum amount',
            'search-category' => 'Category',
            'search-collection' => 'Collection',
        ];

        $browser->assertScript("document.getElementById('search-sort').labels[0].firstChild.textContent.trim()", 'Order this page')
            ->type('#search-amount-min', '0')->type('#search-amount-max', '100');

        foreach ($labels as $id => $label) {
            $browser->assertScript("document.getElementById('$id').labels[0].firstChild.textContent.trim()", $label)
                ->assertVisible('#'.$id);
        }
    });
});

it('recovers from mobile no results by clearing filters while preserving the query', function (): void {
    $user = $this->createUser();
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Filter recovery']);
    Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id, 'merchant_id' => $merchant->id]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(390, 844)->loginAs($user)->visit('/search?query=Filter%20recovery&type=invoice')
            ->waitForText('No results found')->assertSee('Clear filters (keep search)')
            ->press('Clear filters (keep search)')->waitForText('1 result')->waitFor('button[aria-label^="Quick preview of"]')
            ->assertInputValue('input[aria-label="Search document contents"]', 'Filter recovery')
            ->waitUntil("new URL(location.href).searchParams.get('type') === 'all'");
        $browser->visit('/search?query=unmatched-search-identity')->waitForText('No results found')
            ->press('Clear search')->waitForText('Start searching')
            ->assertInputValue('input[aria-label="Search document contents"]', '');
    });
});

it('fits notification identities and footer actions within mobile viewports', function (): void {
    $user = $this->createUser();
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['type' => 'receipt_failed', 'error_message' => str_repeat('long-source-filename-', 15).'.pdf'],
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(390, 844)->loginAs($user)->visit('/dashboard')
            ->waitFor('button[aria-label="View notifications"]')->click('button[aria-label="View notifications"]')
            ->waitFor('[role="menu"] a[href$="/preferences"]');

        foreach ([320, 390] as $width) {
            $browser->resize($width, 844);
            $browser->script("const bell = document.querySelector('[aria-label=\"View notifications\"]').closest('div.relative'); bell.style.position = 'absolute'; bell.style.left = '80px';");

            foreach ([false, true] as $dark) {
                $browser->script('document.documentElement.classList.toggle("dark", '.($dark ? 'true' : 'false').')');
                $browser->assertScript(<<<'JS'
                    (() => {
                        const menu = document.querySelector('[role="menu"]');
                        const rect = menu.getBoundingClientRect();
                        const footer = menu.querySelector('a').getBoundingClientRect();
                        return rect.left >= 16 && rect.right <= window.innerWidth - 16
                            && menu.scrollWidth <= menu.clientWidth
                            && footer.left >= rect.left && footer.right <= rect.right;
                    })()
                    JS, true);
            }
        }
    });
});

it('dismisses notifications with Escape from the trigger or panel and preserves unread state', function (): void {
    $user = $this->createUser();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['type' => 'receipt_failed', 'error_message' => 'Processing failed'],
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $trigger = 'button[aria-label="View notifications"]';
        $browser->loginAs($user)->visit('/dashboard')->waitFor($trigger);

        foreach ([$trigger, '[role="menu"]'] as $focusTarget) {
            $browser->click($trigger)->waitFor('[role="menu"]');
            $browser->script("document.querySelector('$focusTarget').focus()");
            $browser->keys($focusTarget, WebDriverKeys::ESCAPE)
                ->waitUntilMissing('[role="menu"]')
                ->assertScript("document.activeElement === document.querySelector('$trigger')", true)
                ->assertAttribute($trigger, 'aria-expanded', 'false');
        }
    });

    expect($notification->fresh()->read_at)->toBeNull();
});

it('shows default and legacy timezone selections without changing saved values', function (?string $timezone, string $effective): void {
    $user = $this->createUser();
    if ($timezone !== null) {
        UserPreference::create(['user_id' => $user->id, 'timezone' => $timezone]);
    }

    $this->browse(function (Browser $browser) use ($user, $timezone, $effective): void {
        $browser->loginAs($user)->visit('/preferences')->waitFor('#timezone')
            ->assertSelected('#timezone', $timezone ?? 'UTC')
            ->assertSee('Effective timezone: '.$effective);
    });

    expect($user->fresh()->preferences?->timezone)->toBe($timezone);
})->with([
    'absent' => [null, 'UTC'],
    'default' => ['UTC', 'UTC'],
    'supported' => ['Europe/Oslo', 'Europe/Oslo'],
    'legacy' => ['Etc/UTC', 'UTC'],
    'invalid legacy' => ['Invalid/Zone', 'UTC'],
]);

it('discards receipt edits on cancel and saves only through Save Changes', function (): void {
    $user = $this->createUser();
    $receipt = Receipt::factory()->for(File::factory()->for($user)->state(['status' => 'completed']))->create([
        'user_id' => $user->id,
        'total_amount' => 42,
        'receipt_date' => '2026-10-05',
        'currency' => 'NOK',
        'receipt_description' => 'Persisted description',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt): void {
        $browser->loginAs($user)->visit('/receipts/'.$receipt->id)->waitForText('Persisted description')
            ->press('Edit details')->press('Cancel editing')->waitForText('Edit details')
            ->press('Edit details')->type('input[name="total_amount"]', '99')
            ->press('Cancel editing')->waitForText('Edit details');
        expect($receipt->fresh()->total_amount)->toEqual(42);
        $browser->press('Edit details')->assertInputValue('input[name="total_amount"]', '42.00')
            ->type('input[name="total_amount"]', '55')->press('Save changes')->waitForText('Edit details');
    });

    expect($receipt->fresh()->total_amount)->toEqual(55);
});

it('wraps long receipt descriptions and preserves multiline notes while editing', function (): void {
    $user = $this->createUser();
    $description = str_repeat('A readable sentence describing the receipt. ', 5);
    $note = "First line\nSecond line\nThird line";
    $receipt = Receipt::factory()->for(File::factory()->for($user)->state(['status' => 'completed']))->create([
        'user_id' => $user->id, 'receipt_description' => $description, 'note' => $note,
        'receipt_date' => '2026-10-05', 'currency' => 'NOK', 'total_amount' => 42,
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt, $description, $note): void {
        $browser->loginAs($user)->visit('/receipts/'.$receipt->id)->waitForText('Edit details')->press('Edit details')
            ->assertInputValue('textarea[aria-label="Description"]', $description)
            ->assertInputValue('textarea[name="note"]', $note);
        foreach ([320, 390, 1440] as $width) {
            $browser->resize($width, 844)->assertScript(<<<'JS'
                Array.from(document.querySelectorAll('.inspector-details textarea')).every(el =>
                    el.rows >= 4 && el.scrollWidth <= el.clientWidth && getComputedStyle(el).whiteSpace === 'pre-wrap')
                JS, true);
        }
        $browser->type('textarea[name="note"]', $note."\nSaved fourth line")
            ->press('Save changes')->waitForText('Edit details')
            ->refresh()->waitForText('Saved fourth line')->press('Edit details')
            ->assertInputValue('textarea[name="note"]', $note."\nSaved fourth line");
    });
});

it('explains automatic upload classification and accepts supported document and image formats', function (): void {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/documents/upload')->waitForText('We detect the file type')
            ->assertMissing('[aria-label="Upload mode"]');
        $browser->assertScript("document.querySelector('input[type=file]').accept.includes('.docx') && document.querySelector('input[type=file]').accept.includes('.jpg')", true);
        $browser->attach('input[type=file]', base_path('tests/Browser/fixtures/test-image.jpg'))
            ->waitForText('test-image.jpg')->assertSee('Upload 1 file');
    });
});

it('keeps shared upload pickers readable in both themes through focus selection and menus', function (): void {
    $user = $this->createUser();
    Collection::factory()->create(['user_id' => $user->id, 'name' => 'Picker folder', 'color' => '#18181b']);
    Tag::factory()->create(['user_id' => $user->id, 'name' => 'Picker tag', 'color' => '#18181b']);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/documents/upload')->waitForText('2. Add notes and organization')->click('summary')->waitFor('input[placeholder="Search or create collections..."]');
        foreach ([false, true] as $dark) {
            $browser->script('document.documentElement.classList.toggle("dark", '.($dark ? 'true' : 'false').')');
            foreach (['collections' => 'Picker folder', 'tags' => 'Picker tag'] as $kind => $name) {
                $selector = 'input[placeholder="Search or create '.$kind.'..."]';
                $browser->assertScript("getComputedStyle(document.querySelector('$selector').parentElement).backgroundColor", $dark ? 'rgb(24, 24, 27)' : 'rgb(255, 255, 255)');
                $browser->type($selector, 'Picker')->waitFor('.relative:has('.$selector.') > .absolute');
                $browser->assertScript("getComputedStyle(document.querySelector('$selector'), '::placeholder').color", $dark ? 'rgb(161, 161, 170)' : 'rgb(113, 113, 122)');
                $browser->assertScript("getComputedStyle(document.querySelector('$selector').parentElement.nextElementSibling).color", $dark ? 'rgb(244, 244, 245)' : 'rgb(24, 24, 27)');
                $browser->click('.relative:has('.$selector.') > .absolute > div:first-child')->assertSee($name);
                $browser->assertScript("getComputedStyle(document.querySelector('$selector').parentElement.querySelector('span')).color", $dark ? 'rgb(244, 244, 245)' : 'rgb(24, 24, 27)');
                $browser->click('.relative:has('.$selector.') > div > span > button');
            }
        }
    });
});

it('guides export creation and distinguishes empty pending and expired exports', function (): void {
    $user = $this->createUser();
    Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/exports')->waitForText('Export history')->assertSee('0 exports')->assertSee('No exports available.')
            ->assertSee('select the receipts using their checkboxes')
            ->clickLink('Select receipts to export')->waitForLocation('/receipts')
            ->check('thead input[type="checkbox"]')->waitForText('Export')->press('Export')
            ->waitForText('Export as CSV')->assertSee('Export as PDF');

        ArchiveExport::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
        ArchiveExport::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'expires_at' => now()->subMinute()]);
        $readyExport = ArchiveExport::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'processed' => 100, 'expires_at' => now()->addHour()]);
        $browser->visit('/exports')->waitForText('PDF export · pending')
            ->assertSee('3 exports')->assertSee('0 / 100 processed')->assertPresent('[role="status"]')
            ->assertAttribute('progress', 'aria-label', 'PDF export progress')->assertSee('PDF export · expired')
            ->assertSee('This download expired on')->assertSee('Ready to download until')
            ->assertSeeLink('Download PDF export')->assertPresent('a[href*="/exports/'.$readyExport->id.'/download"]')
            ->assertDontSee('No exports available.')->screenshot('exports-history-desktop');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
        }
    });
});

it('sets distinct browser titles with exactly one product suffix', function (): void {
    $user = $this->createUser();
    $titles = [
        '/analytics' => 'Reports overview',
        '/invoices' => 'Invoices',
        '/contracts' => 'Contracts',
        '/bank-statements' => 'Bank statements',
        '/vouchers' => 'Vouchers',
        '/documents/upload' => 'Upload files',
        '/pulsedav' => 'Scanner imports',
        '/scanner' => 'Scan a document',
    ];

    $this->browse(function (Browser $browser) use ($user, $titles): void {
        $browser->loginAs($user);
        foreach ($titles as $url => $title) {
            $browser->visit($url)->waitUntil('document.title === '.json_encode($title.' - PaperPulse'))->assertTitle($title.' - PaperPulse');
        }
        $browser->visit('/')->waitUntil('document.title === "Welcome - PaperPulse"')->assertTitle('Welcome - PaperPulse');
    });
});

it('keeps every theme reachable and indicates the active theme on mobile', function (): void {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/dashboard')->waitFor('header');
        foreach ([320, 390] as $width) {
            $browser->resize($width, 844);
            $browser->script('localStorage.setItem("theme", "system")');
            $browser->refresh()->waitFor('button[title="System theme"]');
            foreach (['System' => 'Dark', 'Dark' => 'Light', 'Light' => 'System'] as $current => $next) {
                $selector = 'button[title="'.$current.' theme"]';
                $browser->assertVisible($selector)
                    ->assertAttribute($selector, 'aria-label', $current.' theme. Switch to '.strtolower($next).' mode');
                $browser->assertScript("(() => { const r = document.querySelector('$selector').getBoundingClientRect(); return r.left >= 0 && r.right <= window.innerWidth; })()", true);
                $browser->click($selector)->waitFor('button[title="'.$next.' theme"]')
                    ->assertScript('localStorage.getItem("theme")', strtolower($next));
                if ($next !== 'System') {
                    $browser->assertScript('document.documentElement.classList.contains("dark")', $next === 'Dark');
                }
            }
            $browser->refresh()->waitFor('button[title="System theme"]');
        }
    });
});

it('shows each receipt membership once and gives optional metadata clear empty states', function (): void {
    $user = $this->createUser();
    $file = File::factory()->for($user)->create(['status' => 'completed']);
    $receipt = Receipt::factory()->for($file)->create(['user_id' => $user->id]);
    $collection = Collection::factory()->for($user)->create(['name' => 'Needs review']);
    $file->collections()->attach($collection);
    $this->browse(function (Browser $browser) use ($user, $receipt): void {
        $browser->loginAs($user)->visit('/receipts/'.$receipt->id)->waitFor('.inspector-details');
        foreach (['Tags', 'Collections'] as $label) {
            $browser->script("Array.from(document.querySelectorAll('.inspector-details summary')).find(el => el.textContent.trim().startsWith('{$label}')).click()");
        }
        $browser->assertSee('Needs review')->assertSee('No tags added.')
            ->assertScript('document.querySelectorAll(".inspector-details a[href*=\"/collections/\"]").length', 1)
            ->press('Edit details')->waitFor('input[placeholder="Add to collections..."]')->assertSee('Folder changes save with Save changes.')
            ->press('Cancel editing')->waitUntilMissing('input[placeholder="Add to collections..."]');
    });
    $file->collections()->detach($collection);
    $this->browse(function (Browser $browser): void {
        $browser->refresh()->waitFor('.inspector-details');
        $browser->script("Array.from(document.querySelectorAll('.inspector-details summary')).find(el => el.textContent.trim().startsWith('Collections')).click()");
        $browser->assertSee('Not assigned to a collection');
    });
});

it('identifies the admin processing analytics browser page', function (): void {
    $user = $this->createUser(['is_admin' => true]);
    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/analytics/processing')->waitForText('Processing analytics')
            ->assertTitleContains('Processing analytics')->assertSee('Counts and averages across recorded processing attempts.')
            ->assertSee('Extraction quality by type')->assertSee('Validation failures by extractor')
            ->assertSee('Daily processing activity');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 900)->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
                ->assertVisible('#processing-period');
        }
    });
});

it('keeps admin job recovery actions contextual and responsive', function (): void {
    $user = $this->createUser(['is_admin' => true]);
    $parentId = (string) Str::uuid();
    JobHistory::create([
        'uuid' => $parentId,
        'name' => 'Process Document',
        'queue' => 'documents',
        'status' => 'failed',
        'file_name' => 'failed-invoice.pdf',
        'exception' => 'Sensitive diagnostic detail',
    ]);
    JobHistory::create([
        'uuid' => (string) Str::uuid(),
        'parent_uuid' => $parentId,
        'name' => 'Extract invoice',
        'queue' => 'documents',
        'status' => 'failed',
        'order_in_chain' => 1,
        'exception' => 'Sensitive diagnostic detail',
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/jobs')->waitForText('Job operations')
            ->assertSee('Find a job')->assertSee('Failed')->assertSee('failed-invoice.pdf')
            ->assertSee('Restart failed chain')->assertDontSee('Sensitive diagnostic detail');

        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 900)->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
                ->assertVisible('select[aria-label="Job status"]')
                ->assertVisible('input[data-job-selection]');
        }

        $browser->check('input[data-job-selection]')->waitForText('1 failed chain selected (maximum 10)')
            ->assertSee('Restart 1 selected')->assertSee('Clear selection');
    });
});

it('reconciles the activity summary including review and pending files', function (): void {
    $user = $this->createUser();
    foreach (['pending', 'processing', 'completed', 'failed', 'needs_review'] as $status) {
        File::factory()->create(['user_id' => $user->id, 'status' => $status]);
    }
    File::factory()->create(['status' => 'needs_review']);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/files-processing')->waitForText('Total Files');
        $browser->assertScript(<<<'JS'
            (() => {
                const counts = {};
                for (const label of document.querySelectorAll('p.text-sm.font-bold')) {
                    counts[label.textContent] = Number(label.nextElementSibling.textContent);
                }
                return counts['Total Files'] === 5 && counts.Ready === 1 && counts.Queued === 1
                    && counts.Processing === 1 && counts.Failed === 1 && counts['Needs review'] === 1;
            })()
            JS, true);
    });
});

it('explains empty direct folder contents and links to populated subfolders', function (): void {
    $user = $this->createUser();
    $parent = Collection::factory()->for($user)->create(['name' => 'Building']);
    $child = Collection::factory()->for($user)->create(['name' => 'Contracts', 'parent_id' => $parent->id]);
    $file = File::factory()->for($user)->create(['fileName' => 'contract.pdf', 'status' => 'completed']);
    $child->files()->attach($file);
    $this->browse(function (Browser $browser) use ($user, $parent, $child): void {
        foreach ([390, 1440] as $width) {
            $browser->resize($width, 900)->loginAs($user)->visit('/collections/'.$parent->id)->waitFor('.file-list')
                ->assertSee('No files directly in this collection')->assertSee('Upload files here or open a subfolder above.')->assertSee('Upload files');
            if ($width < 768) {
                $browser->press('Folders')->waitForText('Browse folders');
            }
            $browser->click(($width < 768 ? '[role="dialog"] ' : '').'section[aria-label="Subfolders"] a[href$="/collections/'.$child->id.'"]')
                ->waitForLocation('/collections/'.$child->id)->waitForText('contract.pdf')->assertDontSee('No files directly in this collection');
        }
    });
    $child->files()->detach($file);
    $this->browse(fn (Browser $browser) => $browser->refresh()->waitForText('No files directly in this collection')->assertSee('Upload files'));
});

it('explains sparse and consecutive observed months in reports', function (): void {
    $user = $this->createUser();
    foreach (['2021-09-01', '2022-11-01', '2024-04-01', '2024-05-01'] as $date) {
        Receipt::factory()->create(['user_id' => $user->id, 'receipt_date' => $date, 'currency' => 'NOK']);
        Invoice::factory()->create(['user_id' => $user->id, 'invoice_date' => $date, 'currency' => 'NOK']);
        Document::factory()->create(['user_id' => $user->id, 'created_at' => $date]);
    }
    $this->browse(function (Browser $browser) use ($user): void {
        foreach (['overview', 'receipts', 'invoices', 'documents'] as $tab) {
            $browser->loginAs($user)->visit('/analytics?tab='.$tab)->waitForText('Observed Month')
                ->assertSee('Only months with records are shown.')
                ->assertSee('Missing months are omitted; spacing does not represent elapsed time.');
        }
    });
});

it('edits receipts using managed categories and an uncategorized option', function (): void {
    $user = $this->createUser();
    $category = Category::create(['user_id' => $user->id, 'name' => 'Garden & Plants', 'slug' => 'garden-plants', 'is_active' => true]);
    $receipt = Receipt::factory()->for(File::factory()->for($user)->state(['status' => 'completed']))->create(['user_id' => $user->id, 'category_id' => $category->id, 'receipt_date' => '2026-10-05', 'total_amount' => 42, 'currency' => 'NOK']);
    $this->browse(function (Browser $browser) use ($user, $receipt, $category): void {
        $browser->loginAs($user)->visit('/receipts/'.$receipt->id)->waitForText('Edit details')
            ->press('Edit details')->waitForText('Save changes')
            ->assertScript("document.querySelector('select').value", (string) $category->id)
            ->assertScript("Array.from(document.querySelector('select').options).map(option => option.textContent.trim())", ['Uncategorized', 'Garden & Plants'])
            ->type('input[name="total_amount"]', '43')->press('Save changes')->waitForText('Edit details')
            ->refresh()->waitForText('Edit details')->press('Edit details')->waitForText('Save changes')
            ->assertScript("document.querySelector('select').value", (string) $category->id)
            ->select('select', 'Uncategorized')->assertScript("document.querySelector('select').selectedIndex", 0)->press('Save changes')->waitForText('Edit details')->refresh()->waitForText('Edit details')
            ->press('Edit details')->assertScript("document.querySelector('select').selectedIndex", 0);
    });
});

it('renders translated expiry scanner and review labels', function (): void {
    $user = $this->createUser();
    $file = File::factory()->create(['user_id' => $user->id, 'status' => 'needs_review']);
    Voucher::factory()->create(['user_id' => $user->id, 'expiry_date' => now()->addDays(10)]);
    Warranty::factory()->create(['user_id' => $user->id, 'warranty_end_date' => now()->addDays(10)]);
    $this->browse(function (Browser $browser) use ($user, $file): void {
        $browser->loginAs($user)->visit('/dashboard')->waitForText('1 expiring voucher')
            ->assertSee('1 warranty ending')->assertSee('Expiring within 30 days')
            ->assertDontSee('No vouchers expiring soon.')->assertDontSee('No warranties ending soon.')
            ->visit('/preferences#preferences-scanner')->waitForText('Scanner Preferences')->assertSee('Auto-process scanner uploads')
            ->visit('/files/'.$file->id.'/extraction-report')->waitForText('Extraction report')
            ->assertSee('Needs review')->assertDontSee('needs_review');
    });
});

it('keeps merchant receipt context when inspecting details and returning from all receipts', function (): void {
    $user = $this->createUser();
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Eik Senteret']);
    Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id, 'merchant_id' => $merchant->id, 'receipt_date' => '2026-10-05', 'total_amount' => 42, 'currency' => 'NOK']);
    $this->browse(function (Browser $browser) use ($user, $merchant): void {
        $path = '/receipts/merchant/'.$merchant->id;
        $browser->loginAs($user)->visit($path)->waitForText('Receipts · Eik Senteret')
            ->assertTitleContains('Eik Senteret')->click('tbody button[dusk^="receipt-preview-"]')->waitFor('[aria-label="Selected document"]')
            ->assertPathIs($path)->assertSee('Receipts · Eik Senteret')
            ->waitUntil('document.querySelector("[aria-label=\"Selected document\"]").getBoundingClientRect().right <= innerWidth + 1')
            ->click('button[aria-label="Close preview"]')->waitUntilMissing('[aria-label="Selected document"]')->clickLink('All Receipts')->waitForLocation('/receipts');
        $browser->back()->waitForLocation($path)->waitForText('Receipts · Eik Senteret');
    });
});

it('saves receipt folder edits and cancels them without changing saved memberships', function (): void {
    $user = $this->createUser();
    $file = File::factory()->for($user)->create(['status' => 'completed']);
    $receipt = Receipt::factory()->for($file)->create(['user_id' => $user->id, 'receipt_date' => '2026-10-05', 'total_amount' => 42, 'currency' => 'NOK']);
    $old = Collection::factory()->for($user)->create(['name' => 'Old Folder']);
    $new = Collection::factory()->for($user)->create(['name' => 'New Folder']);
    $file->collections()->attach($old);
    $this->browse(function (Browser $browser) use ($user, $receipt, $file, $old, $new): void {
        $browser->loginAs($user)->visit('/receipts/'.$receipt->id)->waitFor('.inspector-details');
        foreach (['Cancel editing', 'Save changes'] as $action) {
            $browser->press('Edit details')->waitFor('input[name="total_amount"]');
            $browser->script("const summary = Array.from(document.querySelectorAll('.inspector-details summary')).find(el => el.textContent.trim().startsWith('Collections')); if (!summary.parentElement.open) summary.click()");
            $browser->waitFor('input[placeholder="Add to collections..."]')
                ->waitUntil('document.querySelector("input[placeholder=\"Add to collections...\"]").parentElement.querySelector("span > button") !== null');
            $browser->script('document.querySelector("input[placeholder=\"Add to collections...\"]").parentElement.querySelector("span > button").click()');
            $browser->type('input[placeholder="Add to collections..."]', 'New Folder')
                ->waitFor('.relative:has(input[placeholder="Add to collections..."]) > .absolute')
                ->click('.relative:has(input[placeholder="Add to collections..."]) > .absolute > div:first-child')
                ->press($action)->waitUntilMissing('input[name="total_amount"]');
            expect($file->fresh()->collections->modelKeys())->toBe([$action === 'Cancel editing' ? $old->id : $new->id]);
            $browser->refresh()->waitForText('Document information');
        }
    });
});

it('keeps folder content ahead of collapsed public sharing at desktop and mobile widths', function (): void {
    $user = $this->createUser();
    $folder = Collection::factory()->for($user)->create(['name' => 'Building', 'description' => null]);
    Collection::factory()->for($user)->create(['name' => 'Contracts', 'parent_id' => $folder->id]);
    $this->browse(function (Browser $browser) use ($user, $folder): void {
        foreach ([1440, 390] as $width) {
            $browser->resize($width, 900)->loginAs($user)->visit('/collections/'.$folder->id)->waitFor('.file-list');
            if ($width < 768) {
                $browser->press('Folders')->waitForText('Browse folders');
            }
            $browser->assertScript("Array.from(document.querySelectorAll('section[aria-label=Subfolders] h2')).some(el => el.getBoundingClientRect().height > 0 && el.getBoundingClientRect().bottom < innerHeight)", true);
            if ($width < 768) {
                $browser->press('Close')->waitUntilMissing('[role="dialog"]');
            }
            $browser
                ->press('Details')->waitFor('[data-public-sharing]')
                ->assertScript("document.querySelector('[data-public-sharing]').open", false)
                ->click('[data-public-sharing] summary')->assertSee('Public sharing');
        }
    });
});

it('names the target parent when managing and creating empty subfolders', function (): void {
    $user = $this->createUser();
    $root = Collection::factory()->for($user)->create(['name' => 'Building']);
    $parent = Collection::factory()->for($user)->create(['name' => 'Contracts', 'parent_id' => $root->id]);
    $leaf = Collection::factory()->for($user)->create(['name' => 'Eksempelveien', 'parent_id' => $parent->id]);
    $this->browse(function (Browser $browser) use ($user, $leaf): void {
        $browser->loginAs($user)->visit('/collections/'.$leaf->id)->clickLink('Manage subfolders')
            ->waitForText('No subfolders in Eksempelveien')->assertSee('Building')->assertSee('Contracts')
            ->press('Create Subfolder')->waitForText('Create subfolder in Eksempelveien')
            ->type('#collection-name', 'Plans')->press('Create')->waitUntilMissing('#collection-name')
            ->assertSee('Plans');
        $browser->clickLink('Back to Eksempelveien')->waitForLocation('/collections/'.$leaf->id);
        expect(Collection::where('user_id', $user->id)->where('name', 'Plans')->sole()->parent_id)->toBe($leaf->id);
    });
});

it('reaches common settings through labelled sections on mobile and desktop', function (): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user): void {
        foreach ([1440, 768, 390, 320] as $width) {
            $browser->resize($width, 900)->loginAs($user)->visit('/preferences')->waitFor('nav[aria-label="Settings sections"]');
            $browser->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->assertScript("Array.from(document.querySelectorAll('nav[aria-label^=Settings] a')).every(link => { const rect = link.getBoundingClientRect(); return rect.height >= 44 && rect.right <= innerWidth; })", true);
            if ($width === 320) {
                $browser->screenshot('settings-sections-mobile');
            }
            $browser->script('document.querySelector(\'a[href="#preferences-general"]\').focus()');
            $browser->keys('nav[aria-label="Settings sections"] a[href="#preferences-general"]', WebDriverKeys::ENTER)
                ->assertScript('location.hash', '#preferences-general');
            foreach (['general', 'processing', 'notifications', 'display', 'scanner', 'organization', 'archive'] as $id) {
                $browser->script("document.querySelector('a[href=\"#preferences-{$id}\"]').click()");
                $browser
                    ->assertScript("(() => { const top = document.querySelector('#preferences-".$id."').getBoundingClientRect().top; return top >= 0 && top < innerHeight; })()", true);
            }
        }
    });
});

it('fits portrait and multipage PDF sources in previews at different widths', function (): void {
    $user = $this->createUser();
    $multiPage = new Dompdf;
    $multiPage->loadHtml('<p>First page</p><p style="page-break-before: always">Second page</p>');
    $multiPage->render();
    expect((new Parser)->parseContent($multiPage->output())->getPages())->toHaveCount(2);
    foreach ([
        'real-receipt.pdf' => file_get_contents(base_path('tests/Browser/fixtures/real-receipt.pdf')),
        'real-invoice.pdf' => file_get_contents(base_path('tests/Browser/fixtures/real-invoice.pdf')),
        'multipage.pdf' => $multiPage->output(),
    ] as $fixture => $contents) {
        $file = File::factory()->for($user)->create(['fileName' => $fixture, 'file_type' => 'document', 'fileExtension' => 'pdf', 'status' => 'completed']);
        $path = StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf');
        Storage::disk('paperpulse')->put($path, $contents);
        $file->update(['s3_original_path' => $path]);
        try {
            $this->browse(function (Browser $browser) use ($user, $file): void {
                foreach ([1440, 768, 390] as $width) {
                    $browser->resize($width, 900)->loginAs($user)->visit('/files/'.$file->id)->waitFor('iframe')
                        ->assertScript("(() => { const hash = new URL(document.querySelector('iframe').src).hash; return hash.includes('navpanes=0') && hash.includes('view=Fit'); })()", true)
                        ->assertScript("document.querySelector('iframe').getBoundingClientRect().width <= innerWidth", true)
                        ->assertPresent('a[target="_blank"]');
                }
            });
        } finally {
            Storage::disk('paperpulse')->delete($path);
        }
    }
});

it('opens scanner connection instructions and processing settings from empty imports', function (): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/pulsedav')->waitForText('No scanner imports yet')
            ->assertSee('private scanner inbox')->click('summary')->assertSee('WebDAV server address')
            ->assertSee('verified PaperPulse email address and password')
            ->clickLink('Scanner processing settings')->waitForLocation('/preferences')
            ->assertScript('location.hash', '#preferences-scanner');
    });
});

it('keeps recent receipt merchant and amount pairs visible at phone widths', function (): void {
    $user = $this->createUser();
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Long merchant name with multiple words']);
    foreach ([42, 1234.56] as $amount) {
        $receipt = Receipt::factory()->for(File::factory()->for($user)->state(['status' => 'completed']))->create(['user_id' => $user->id, 'merchant_id' => $merchant->id,
            'receipt_date' => '2026-10-06', 'total_amount' => $amount, 'currency' => 'EUR', 'receipt_category' => 'Groceries']);
        ExtractableEntity::factory()->create(['user_id' => $user->id, 'file_id' => $receipt->file_id, 'entity_type' => 'receipt', 'entity_id' => $receipt->id, 'is_primary' => true]);
    }
    $this->browse(function (Browser $browser) use ($user): void {
        foreach ([320, 390] as $width) {
            $browser->resize($width, 844)->loginAs($user)->visit('/dashboard')->waitFor('tbody tr')
                ->assertSee('€42.00')->assertSee('€1,234.56')
                ->assertScript("Array.from(document.querySelectorAll('tbody tr td:has(.tabular-nums), tbody tr td.tabular-nums')).every(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth && r.width > 0; })", true)
                ->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
        }
    });
});

it('stacks entity details and keeps actions within phone and tablet widths', function (): void {
    $user = $this->createUser();
    $file = File::factory()->for($user)->create();
    $receipt = Receipt::factory()->for($file)->create(['user_id' => $user->id]);
    $invoice = Invoice::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id,
        'invoice_number' => 'LONG-INVOICE-1234567890',
        'from_name' => 'A long supplier name', 'from_email' => str_repeat('supplier', 8).'@example.com',
        'to_name' => 'A long customer name', 'to_address' => 'An extended street address',
    ]);
    $this->browse(function (Browser $browser) use ($user, $receipt, $invoice): void {
        foreach (['/receipts/'.$receipt->id, '/invoices/'.$invoice->id] as $path) {
            foreach ([320, 390, 768] as $width) {
                $browser->resize($width, 844)->loginAs($user)->visit($path)->waitFor('#main-content');
                if (str_starts_with($path, '/receipts/')) {
                    $browser->waitFor('.inspector-details')->script("Array.from(document.querySelectorAll('.inspector-details summary')).find(el => el.textContent.trim() === 'Sharing').click()");
                }
                $browser->waitFor('.sharing-controls > button');
                $browser->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                    ->assertScript(<<<'JS'
                        Array.from(document.querySelectorAll('header button, header a')).filter(el => el.getBoundingClientRect().width).every(el => {
                            const r = el.getBoundingClientRect();
                            return r.left >= 0 && r.right <= innerWidth;
                        })
                        JS, true)
                    ->click('.sharing-controls > button')->waitFor('[role="dialog"]')->keys('[role="dialog"] input', '{escape}')
                    ->waitUntilMissing('[role="dialog"] input');
            }
        }
    });
});

it('keeps long receipt details scrollable and expands invoice tables when hiding the source', function (): void {
    $user = $this->createUser();
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id]);
    foreach (range(1, 50) as $number) {
        LineItem::create(['receipt_id' => $receipt->id, 'text' => 'Receipt item '.$number, 'qty' => 1, 'price' => 10, 'total' => 10]);
    }
    $invoice = Invoice::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id]);
    InvoiceLineItem::factory()->count(50)->create(['invoice_id' => $invoice->id, 'description' => 'Long invoice line description']);
    $this->browse(function (Browser $browser) use ($user, $receipt, $invoice): void {
        $browser->resize(1440, 900)->loginAs($user)->visit('/receipts/'.$receipt->id)->waitFor('.inspector-details');
        $browser->script("Array.from(document.querySelectorAll('.inspector-details summary')).find(el => el.textContent.includes('Line items')).click()");
        $browser->assertScript('document.querySelector(".inspector-details").scrollHeight > document.querySelector(".inspector-details").clientHeight', true)
            ->assertScript('document.documentElement.scrollHeight <= innerHeight', true)
            ->keys('.inspector-details', WebDriverKeys::END)->waitUntil('document.querySelector(".inspector-details").scrollTop > 0')
            ->assertSee('Receipt item 50')->assertScript('document.querySelector(".file-inspector header").getBoundingClientRect().top >= 0', true);
        $browser->visit('/invoices/'.$invoice->id)->waitForText('Hide source preview')
            ->press('Hide source preview')->waitForText('Show source preview')
            ->assertScript("document.querySelector('table').parentElement.clientWidth > 950", true)
            ->scrollIntoView('table')->keys('div[tabindex="0"]', WebDriverKeys::END);
        $browser->script('window.scrollTo(0, 0)');
        $browser->press('Show source preview')->waitForText('Hide source preview');
    });
});

it('identifies independent settings save scopes and tracks their pending changes', function (): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/preferences')->waitForText('Save application preferences')
            ->assertSee('No unsaved application preferences')->assertSee('No unsaved organization choices')
            ->select('#currency', 'EUR')->waitForText('Unsaved application preferences')
            ->assertSee('No unsaved organization choices');
        $browser->script('document.querySelector("#preferences-organization input").scrollIntoView({block: "center"})');
        $browser->type('#preferences-organization input', 'Properties')->waitForText('Unsaved organization choices');
        $browser->script('document.querySelector("[dusk=save-application-preferences]").scrollIntoView({block: "center"})');
        $browser->click('@save-application-preferences')->waitForText('Application preferences saved')
            ->assertSee('Unsaved organization choices');
        expect($user->fresh()->preference('currency'))->toBe('EUR');
        $browser->script('document.querySelector("#preferences-organization button[type=submit]").scrollIntoView({block: "center"})');
        $browser->click('@save-organization-choices')->waitForText('Organization choices saved')
            ->assertSee('Preview reads your archive without saving settings or moving files.')
            ->refresh()->waitForText('No unsaved organization choices')
            ->assertSelected('#currency', 'EUR')->assertInputValue('#preferences-organization input', 'Properties');
    });
});

it('explains archive work limits and the disabled organize prerequisite in both themes', function (): void {
    $user = $this->createUser();
    UserPreference::create(['user_id' => $user->id, 'auto_organize_documents' => false]);
    File::factory()->count(15)->for($user)->create(['status' => 'completed', 'organization_summary' => null]);
    $this->browse(function (Browser $browser) use ($user): void {
        foreach (['light', 'dark'] as $theme) {
            $browser->loginAs($user)->visit('/preferences')->waitFor('#preferences-archive');
            $browser->script("document.documentElement.classList.toggle('dark', '".$theme."' === 'dark'); document.querySelector('#preferences-archive').scrollIntoView({block: 'start'})");
            $browser->assertSee('Preview the archive before organizing it.')
                ->assertScript("document.querySelector('#preferences-archive details').open", false)
                ->assertScript("document.querySelector('#preferences-archive button[aria-describedby]').disabled", true)
                ->assertSee('With AI off, organizing saved information uses no paid AI requests.');
            $browser->click('#preferences-archive button')->waitForText('15 eligible documents')->assertSee('0 paid AI requests')
                ->assertSee('Enable automatic organization and save application preferences first.')
                ->click('#preferences-archive > label input')->waitForText('AI can attempt up to 10 documents')
                ->assertSee('Provider prices vary; this limit caps work, not a monetary charge.')
                ->click('#preferences-archive summary')->assertSee('Maximum reserved tokens');
        }
    });
});

it('explains the receipt table and library display scopes without offering an unused grid default', function (): void {
    $user = $this->createUser();
    UserPreference::create(['user_id' => $user->id, 'receipts_per_page' => 10, 'default_sort' => 'date_asc']);
    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/preferences')->waitFor('#receipts_per_page')
            ->assertMissing('#receipt_list_view')->assertSelected('#receipts_per_page', '10')
            ->assertSelected('#default_sort', 'date_asc')->assertSee('Page size and sorting apply to the receipt table')
            ->visit('/receipts')->waitForText('Receipt overview · Table view.')
            ->click('a[href$="/library?type=receipt"]')->waitForLocation('/library')->waitFor('@library-query')->assertPresent('select[aria-label="Sort files"]')
            ->click('button[aria-label="Grid view"]')->waitUntil("document.querySelector('button[aria-label=\"Grid view\"]').getAttribute('aria-pressed') === 'true'")
            ->refresh()->waitUntil("document.querySelector('button[aria-label=\"Grid view\"]').getAttribute('aria-pressed') === 'true'")
            ->click('button[aria-label="List view"]')->waitUntil("document.querySelector('button[aria-label=\"List view\"]').getAttribute('aria-pressed') === 'true'");
    });
});

it('distinguishes direct mixed file counts from the folder subtree total', function (): void {
    $user = $this->createUser();
    $root = Collection::factory()->for($user)->create(['name' => 'Building']);
    $child = Collection::factory()->for($user)->create(['name' => 'Contracts', 'parent_id' => $root->id]);
    $leaf = Collection::factory()->for($user)->create(['name' => 'Eksempelveien', 'parent_id' => $child->id]);
    $document = Document::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id]);
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id]);
    $invoice = Invoice::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id]);
    foreach ([$document, $receipt, $invoice] as $entity) {
        $entity->file->extractableEntities()->create(['user_id' => $user->id, 'entity_type' => $entity->getMorphClass(), 'entity_id' => $entity->id, 'is_primary' => true, 'extracted_at' => now()]);
        $leaf->files()->attach($entity->file_id);
    }
    $this->browse(function (Browser $browser) use ($user, $root, $leaf): void {
        $browser->loginAs($user)->visit('/collections')->waitForText('Building')
            ->assertSee('direct files')->assertSee('1 subfolder');
        $browser->visit('/collections/'.$root->id)->press('Details')->waitFor('[data-folder-details]')->waitForText('Files including subfolders')
            ->assertScript("Object.fromEntries(Array.from(document.querySelectorAll('dl > div')).map(el => [el.querySelector('dt')?.textContent, el.querySelector('dd')?.textContent]))['Files including subfolders']", '3')
            ->assertScript("Array.from(document.querySelectorAll('dt')).find(el => el.textContent === 'Files directly here').nextElementSibling.textContent", '0')
            ->visit('/collections/'.$leaf->id)->press('Details')->waitFor('[data-folder-details]')->waitForText('Documents directly here');
        foreach (['Documents directly here', 'Receipts directly here', 'Invoices directly here'] as $label) {
            $browser->assertScript("Array.from(document.querySelectorAll('dt')).find(el => el.textContent === '".$label."').nextElementSibling.textContent", '1');
        }
        $leaf->files()->detach();
        $browser->refresh()->press('Details')->waitFor('[data-folder-details]')->waitForText('Files including subfolders')
            ->assertScript("Array.from(document.querySelectorAll('dt')).find(el => el.textContent === 'Files including subfolders').nextElementSibling.textContent", '0');
    });
});

it('browses folder cards and retains nested context at desktop and mobile widths', function (): void {
    $user = $this->createUser();
    $root = Collection::factory()->for($user)->create(['name' => 'Building']);
    $child = Collection::factory()->for($user)->create(['name' => 'Contracts', 'parent_id' => $root->id]);
    $leaf = Collection::factory()->for($user)->create(['name' => 'Eksempelveien', 'parent_id' => $child->id]);
    $this->browse(function (Browser $browser) use ($user, $root, $child, $leaf): void {
        foreach ([1440, 390] as $width) {
            $browser->resize($width, 900)->loginAs($user)->visit('/collections')->waitForText('Building')
                ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->assertPresent('button[aria-label="Actions for Building"]')
                ->click('a[aria-label="Browse 1 subfolders in Building"]')->waitForText('Subfolders in Building')
                ->assertQueryStringHas('parent_id', (string) $root->id)
                ->click('article a[href$="/collections/'.$child->id.'"]')->waitForLocation('/collections/'.$child->id);
            if ($width < 768) {
                $browser->press('Folders')->waitForText('Browse folders');
            }
            $browser->click(($width < 768 ? '[role="dialog"] ' : '').'section[aria-label="Subfolders"] a[href$="/collections/'.$leaf->id.'"]')->waitForLocation('/collections/'.$leaf->id)
                ->visit('/collections?parent_id='.$child->id)->waitForText('Subfolders in Contracts')->assertSee('Building')->assertSee('Contracts')->assertSee('Eksempelveien')
                ->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
        }
    });
});

it('names active dialogs with their headings and restores focus after Escape', function (string $path, string $trigger, string $heading): void {
    $user = $this->createUser();
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id]);
    $path = $path === 'receipt' ? '/receipts/'.$receipt->id : $path;
    $this->browse(function (Browser $browser) use ($user, $path, $trigger, $heading): void {
        $browser->loginAs($user)->visit($path);
        if (str_starts_with($path, '/receipts/')) {
            $browser->waitFor('.inspector-details')->script("Array.from(document.querySelectorAll('.inspector-details summary')).find(el => el.textContent.trim() === 'Sharing').click()");
        }
        $browser->waitFor($trigger)
            ->assertMissing('[role="dialog"]')->click($trigger)->waitFor('[role="dialog"] input')
            ->assertScript(<<<'JS'
                (() => {
                    const dialogs = document.querySelectorAll('[role="dialog"]');
                    const dialog = dialogs[0];
                    const title = document.getElementById(dialog?.getAttribute('aria-labelledby'));
                    return dialogs.length === 1 && dialog.contains(title) && title.textContent.trim();
                })()
                JS, $heading)
            ->assertScript("document.querySelector('[role=dialog]').contains(document.activeElement)", true)
            ->keys('[role="dialog"] input', '{escape}')->waitUntilMissing('[role="dialog"]')
            ->assertScript("document.activeElement.matches('".$trigger."')", true);
    });
})->with([
    ['/collections', 'main header button.workspace-primary', 'Create New Collection'],
    ['receipt', '.sharing-controls > button', 'Share Receipt'],
    ['/tags', 'button:has(svg path[d="M12 4v16m8-8H4"])', 'Create New Tag'],
    ['/library', '[dusk=save-view]', 'Save a view'],
    ['/search', '[dusk=save-view]', 'Save a view'],
]);

it('discloses native currency range semantics before applying search filters', function (): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user): void {
        foreach ([1440, 390] as $width) {
            $browser->resize($width, 900)->loginAs($user)->visit('/search');

            $browser->waitFor('#search-amount-min');
            $browser->assertSee('Amount · Source currency')
                ->assertSee('No currency conversion: 100 can mean 100 NOK, EUR or USD.')
                ->assertScript("['search-amount-min', 'search-amount-max'].every(id => document.getElementById(id).getAttribute('aria-describedby') === 'search-amount-basis')", true);
        }
    });
});

it('distinguishes banking report loading missing sources empty periods and failed requests', function (): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(1440, 900)->loginAs($user)->visit('/analytics?tab=banking')->waitForText('No bank statements uploaded')
            ->assertSee('Upload a bank statement')->assertMissing('canvas')
            ->assertDontSee('Money In vs Money Out');
        $statement = BankStatement::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id]);
        $browser->refresh()->waitForText('No bank transactions available')->assertSee('no dated transactions to report');
        BankTransaction::factory()->create(['bank_statement_id' => $statement->id, 'user_id' => $user->id,
            'transaction_date' => '2020-01-01', 'amount' => 42, 'currency' => 'NOK', 'balance_after' => 800,
        ]);
        $browser->refresh()->waitFor('canvas')->assertSee('Money In vs Money Out')->assertSee('No data available');
        $browser->script(<<<'JS'
            window.reportOriginalSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function(...args) {
                setTimeout(() => window.reportOriginalSend.apply(this, args), 800);
            };
            JS);
        $browser->select('select', 'month')->waitForText('Loading report…')
            ->waitForText('No bank transactions in the selected period')->assertMissing('canvas');
        $browser->script(<<<'JS'
            XMLHttpRequest.prototype.send = window.reportOriginalSend;
            window.reportOriginalOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function(method, url, ...args) {
                return window.reportOriginalOpen.call(this, method, '/missing-banking-report', ...args);
            };
            JS);
        $browser->select('select', 'all')->waitForText('The report could not be loaded. Try again.')
            ->assertDontSee('No bank statements uploaded')->assertMissing('canvas');
        $browser->script('XMLHttpRequest.prototype.open = window.reportOriginalOpen');
        $browser->press('Try again')->waitFor('canvas')->assertDontSee('The report could not be loaded.')
            ->assertScript("document.querySelector('canvas').height > 0", true);
    });
});

it('renders report statistic labels and zero or populated values with the production runtime', function (bool $populated): void {
    $user = $this->createUser();
    if ($populated) {
        Receipt::factory()->create(['user_id' => $user->id]);
        Invoice::factory()->create(['user_id' => $user->id]);
        $statement = BankStatement::factory()->create(['user_id' => $user->id]);
        BankTransaction::factory()->create(['user_id' => $user->id, 'bank_statement_id' => $statement->id]);
        Contract::factory()->create(['user_id' => $user->id]);
        Document::factory()->create(['user_id' => $user->id]);
    }
    $this->browse(function (Browser $browser) use ($user, $populated): void {
        $this->loginAs($browser, $user);
        foreach (['receipts' => 'Receipts', 'invoices' => 'Invoices', 'banking' => 'Statements', 'contracts' => 'Total Contracts', 'documents' => 'Total Documents'] as $tab => $label) {
            $browser->visit('/analytics?tab='.$tab)->waitFor('dt')->assertSee($label);
            $browser->assertScript('document.querySelector("dt + dd").textContent', $populated ? '1' : '0');
        }
        $logs = $browser->driver->manage()->getLog('browser');
        expect(collect($logs)->pluck('message')->implode(' '))->not->toContain('runtime compilation');
    });
})->with([false, true]);

it('prints structured reports with period currency and accessible chart data on narrow screens', function (): void {
    $user = $this->createUser();
    Receipt::factory()->create(['user_id' => $user->id, 'receipt_date' => '2026-09-27', 'total_amount' => 42, 'currency' => 'NOK']);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->resize(390, 844)->visit('/analytics')->waitForText('Report coverage')
            ->assertSee('Reports overview')->assertSee('All time')->assertSee('Financial totals are shown in NOK')
            ->assertSee('Receipt and invoice totals by Observed Month')->assertSee('Monthly receipt and invoice totals data')
            ->assertPresent('div[aria-label="Scrollable report coverage table"]')
            ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
            ->assertScript("Array.from(document.styleSheets).some(sheet => { try { return Array.from(sheet.cssRules).some(rule => rule.conditionText === 'print'); } catch { return false; } })", true)
            ->script('window.reportPrintCalled = false; window.print = () => { window.reportPrintCalled = true; }');
        $browser->press('Print report')->assertScript('window.reportPrintCalled', true)->assertPathIs('/analytics')
            ->click('.report-controls [aria-pressed]:nth-of-type(2)')->waitForText('Receipts report')->assertSee('All time')
            ->assertSee('Monthly receipt totals data');
    });
});

it('renders both duplicate candidates including files with missing extracted metadata', function (): void {
    $user = $this->createUser();
    $first = File::factory()->create(['user_id' => $user->id, 'fileName' => 'First scan.pdf', 'status' => 'completed']);
    $second = File::factory()->create(['user_id' => $user->id, 'fileName' => 'Second scan.pdf', 'status' => 'processing']);
    $document = Document::factory()->create(['user_id' => $user->id, 'file_id' => $first->id, 'title' => 'Source agreement']);
    ExtractableEntity::create(['user_id' => $user->id, 'file_id' => $first->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true, 'extracted_at' => now()]);
    DuplicateFlag::create(['user_id' => $user->id, 'file_id' => $first->id, 'duplicate_file_id' => $second->id, 'reasons' => ['matching_hash'], 'status' => 'open']);
    $this->browse(function (Browser $browser) use ($user, $first, $second): void {
        $this->loginAs($browser, $user);
        $browser->visit('/duplicates')->waitForText('First scan.pdf')->assertSee('Second scan.pdf')->assertSee('Source agreement')
            ->assertSee('First file')->assertSee('Possible duplicate')->assertSee('Open original')
            ->assertPresent('a[href$="/files/'.$first->id.'"]')->assertPresent('a[href$="/files/'.$second->id.'"]');
        $browser->resize(390, 844)->assertSee('First scan.pdf')->assertSee('Second scan.pdf');
        $browser->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    });
});

it('distinguishes queued active limited and review states and corrects uncertain file types', function (): void {
    $user = $this->createUser();
    File::factory()->create(['user_id' => $user->id, 'status' => 'pending', 'fileName' => 'Queued source.pdf']);
    File::factory()->create(['user_id' => $user->id, 'status' => 'processing', 'fileName' => 'Active source.pdf']);
    $limited = File::factory()->create(['user_id' => $user->id, 'status' => 'needs_review', 'fileName' => 'Limited source.pdf', 'meta' => ['review' => ['reason' => 'processing_limit', 'page_limit' => 3]]]);
    $uncertain = File::factory()->create(['user_id' => $user->id, 'status' => 'needs_review', 'file_type' => 'document', 'fileName' => 'Uncertain source.pdf', 'meta' => ['review' => ['reason' => 'uncertain_classification', 'confidence' => 0.4, 'reasoning' => 'Choose a document type.']]]);
    File::factory()->create(['user_id' => $user->id, 'status' => 'failed', 'fileName' => 'Failed source.pdf']);
    $nonRetryable = File::factory()->create(['user_id' => $user->id, 'status' => 'failed', 'fileName' => 'Support required.pdf', 'meta' => ['gemini_error' => ['category' => 'usage_budget_exceeded', 'retryable' => false]]]);

    $this->browse(function (Browser $browser) use ($user, $nonRetryable): void {
        $browser->loginAs($user)->visit('/files-processing')->waitForText('Processing activity')
            ->assertSee('Queued')->assertSee('Processing')->assertSee('Blocked: processing limit')
            ->assertPresent('button.workspace-primary')
            ->assertSee('This failure is not marked as retryable. Review the guidance before taking another action.')
            ->assertPresent('a[href$="/files/'.$nonRetryable->id.'/extraction-report"]')
            ->assertScript("document.querySelector('[dusk=activity-file-".$nonRetryable->id."] button.workspace-primary') === null", true)
            ->assertPresent('select[aria-label="Correct document type for Uncertain source.pdf"]');
        foreach ([390, 320] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
        }
        $browser->select('select[aria-label="Correct document type for Uncertain source.pdf"]', 'invoice')
            ->click('@uncertain-type-submit')->waitForText('Corrected type queued for extraction.');
    });

    expect($uncertain->fresh()->file_type)->toBe('invoice')
        ->and($uncertain->fresh()->status)->toBe('pending')
        ->and($limited->fresh()->status)->toBe('needs_review');
});

it('keeps scanner folder navigation usable for empty lists and failed requests', function (bool $fails): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user, $fails): void {
        $this->loginAs($browser, $user);
        $browser->visit('/pulsedav')->waitForText('BROWSE FOLDERS');
        $browser->script('window.fetch = async () => new Response('.json_encode($fails ? '{"error":"Unavailable"}' : '{"hierarchy":[]}').', {status: '.($fails ? 503 : 200).', headers: {"Content-Type":"application/json"}})');
        $browser->press('BROWSE FOLDERS')->waitForText($fails ? 'Scanner folders could not be loaded' : 'No files or folders found')->assertSee('Scanner inbox');
        if ($fails) {
            $browser->script('window.fetch = async () => new Response(JSON.stringify({hierarchy:[]}), {status:200, headers:{"Content-Type":"application/json"}})');
            $browser->press('Try again')->waitForText('No files or folders found')->assertMissing('[role="alert"]');
        }
    });
})->with([false, true]);

it('keeps scanner inbox sync feedback and folder selection context clear', function (): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user): void {
        $this->loginAs($browser, $user);
        $browser->visit('/pulsedav')->waitForText('CHECK SCANNER INBOX');
        $browser->script(<<<'JS'
            const originalFetch = window.fetch;
            window.fetch = (url, options) => {
                const urlText = String(url);
                if (urlText.includes('/folder-contents')) {
                    window.lastFolderPath = new URL(urlText, location.href).searchParams.get('folder_path');
                    window.folderAttempts = (window.folderAttempts || 0) + 1;
                    const unavailable = window.folderAttempts === 1;
                    const data = unavailable
                        ? { error: 'Unavailable' }
                        : { contents: [{ path: 'incoming/1/Taxes/2026.pdf', name: '2026.pdf', filename: '2026.pdf', s3_path: 'incoming/1/Taxes/2026.pdf', folder_path: 'Taxes', is_folder: false, status: 'pending' }] };
                    return Promise.resolve(new Response(JSON.stringify(data), { status: unavailable ? 503 : 200, headers: { 'Content-Type': 'application/json' } }));
                }
                if (urlText.endsWith('/import')) {
                    window.lastImportPayload = JSON.parse(options.body);
                    return Promise.resolve(new Response(JSON.stringify({ error: 'Test import failure' }), { status: 422, headers: { 'Content-Type': 'application/json' } }));
                }
                if (urlText.endsWith('/sync-folders')) {
                    return Promise.resolve(new Response(JSON.stringify({ message: 'Scanner inbox checked. No new files found.', synced: 0 }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
                }
                if (urlText.endsWith('/folders')) {
                    return Promise.resolve(new Response(JSON.stringify({ hierarchy: [{ path: 'Taxes', name: 'Taxes', is_folder: true, metadata: { s3_path: 'incoming/1/Taxes/' } }, { path: 'root.pdf', name: 'root.pdf', filename: 'root.pdf', s3_path: 'root.pdf', is_folder: false, status: 'pending' }] }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
                }
                return originalFetch(url, options);
            };
            JS);
        $browser->press('CHECK SCANNER INBOX')->waitFor('[role="status"]')->assertSee('No new files found.');
        $browser->press('BROWSE FOLDERS')->waitFor('input[aria-label="Select Taxes"]')
            ->check('input[aria-label="Select Taxes"]')->waitForText('0 files and 1 folder selected.')
            ->click('button[aria-label="Open folder Taxes"]')->waitFor('[role="alert"]')->assertSee('This folder could not be loaded')
            ->press('Try again')->waitFor('input[aria-label="Select 2026.pdf"]')
            ->assertSee('Current folder: Taxes.')
            ->assertScript('window.folderAttempts', 2)
            ->assertScript('window.lastFolderPath', 'Taxes');
        $browser->check('input[aria-label="Select 2026.pdf"]')
            ->waitForText('1 file and 1 folder selected.')
            ->click('summary')->assertPresent('select#scanner-file-type')
            ->select('select#scanner-file-type', 'document')
            ->press('IMPORT 1 FILE AND 1 FOLDER')->waitForText('Test import failure')
            ->assertScript('window.lastImportPayload.selections[0].s3_path', 'incoming/1/Taxes/')
            ->assertScript('window.lastImportPayload.selections[1].s3_path', 'incoming/1/Taxes/2026.pdf')
            ->assertScript('window.lastImportPayload.file_type', 'document');
        foreach ([390, 320] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
        }
    });
});

it('keeps list processing on the existing scanner processing contract', function (): void {
    $user = $this->createUser();
    $file = PulseDavFile::create([
        'user_id' => $user->id,
        's3_path' => 'incoming/'.$user->id.'/process-me.pdf',
        'filename' => 'process-me.pdf',
        'folder_path' => null,
        'status' => 'pending',
        'is_folder' => false,
        'size' => 10,
        'uploaded_at' => now(),
    ]);

    $this->browse(function (Browser $browser) use ($user, $file): void {
        $this->loginAs($browser, $user);
        $browser->visit('/pulsedav')->waitForText('process-me.pdf');
        $browser->script('const originalFetch = window.fetch; window.fetch = (url, options) => String(url).endsWith("/process") ? (window.lastProcessPayload = JSON.parse(options.body), Promise.resolve(new Response(JSON.stringify({message:"Queued 1 files for processing", queued:1}), {status:200, headers:{"Content-Type":"application/json"}}))) : originalFetch(url, options)');
        $browser->check('tbody input[value="'.$file->id.'"]')
            ->select('select[aria-label="Process selected files as"]', 'document')
            ->press('PROCESS 1 FILE')->waitFor('[role="status"]')->assertSee('Queued 1 files for processing')
            ->assertScript('window.lastProcessPayload.file_ids[0]', $file->id)
            ->assertScript('window.lastProcessPayload.file_type', 'document');
    });
});

it('opens named scanner folder tags and discards canceled edits while saving confirmed tags', function (): void {
    $user = $this->createUser();
    $tag = Tag::create(['user_id' => $user->id, 'name' => 'scanner-tax', 'color' => '#123456']);
    $this->browse(function (Browser $browser) use ($user, $tag): void {
        $this->loginAs($browser, $user);
        $browser->visit('/pulsedav')->waitForText('BROWSE FOLDERS');
        $browser->script('const originalFetch = window.fetch; window.fetch = (url, options) => String(url).endsWith("/pulsedav/folders") ? Promise.resolve(new Response(JSON.stringify({hierarchy:[{path:"Taxes", name:"Taxes", is_folder:true, folder_tag_ids:[]}]}), {status:200, headers:{"Content-Type":"application/json"}})) : originalFetch(url, options)');
        $browser->press('BROWSE FOLDERS')->waitFor('button[title="Manage folder tags"]')->click('button[title="Manage folder tags"]')
            ->waitFor('[role="dialog"] h2')->assertSeeIn('[role="dialog"]', 'Manage Folder Tags: Taxes');
        $browser->assertScript('document.querySelector("[role=dialog]").getAttribute("aria-labelledby") === document.querySelector("[role=dialog] h2").id', true);
        $browser->type('[role="dialog"] input', 'scanner-tax')->pause(200)->keys('[role="dialog"] input', WebDriverKeys::ENTER)
            ->assertSeeIn('[role="dialog"]', 'scanner-tax')->press('Cancel')->waitUntilMissing('[role="dialog"]')
            ->click('button[title="Manage folder tags"]')->waitFor('[role="dialog"] h2')->assertScript("document.querySelector('[role=dialog] input').parentElement.querySelectorAll('span.inline-flex').length", 0)
            ->type('[role="dialog"] input', 'scanner-tax')->pause(200)->keys('[role="dialog"] input', WebDriverKeys::ENTER)->press('Save Tags')->waitUntilMissing('[role="dialog"]');
        $browser->waitUsing(5, 100, fn (): bool => PulseDavFile::where('user_id', $user->id)->where('folder_path', 'Taxes')->exists());
        expect(PulseDavFile::where('user_id', $user->id)->where('folder_path', 'Taxes')->firstOrFail()->folder_tag_ids)->toBe([$tag->id]);
    });
});

it('retains nested folder context in upload library and search selectors', function (): void {
    $user = $this->createUser();
    $root = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Building']);
    $parent = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Contracts', 'parent_id' => $root->id]);
    $leaf = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Eksempelveien', 'parent_id' => $parent->id]);
    $this->browse(function (Browser $browser) use ($user): void {
        $this->loginAs($browser, $user);
        $browser->visit('/documents/upload')->waitForText('2. Add notes and organization')->click('summary')->waitFor('input[placeholder="Search or create collections..."]')
            ->type('input[placeholder="Search or create collections..."]', 'Building')->pause(200)
            ->assertSee('Building → Contracts → Eksempelveien')->keys('input[placeholder="Search or create collections..."]', WebDriverKeys::ARROW_DOWN);
        $browser->type('input[placeholder="Search or create collections..."]', 'Eksempelveien')->keys('input[placeholder="Search or create collections..."]', WebDriverKeys::ENTER)
            ->assertSee('Building → Contracts → Eksempelveien');
        $browser->visit('/library')->waitFor('@library-query')->press('Filters')->assertSee('Building → Contracts → Eksempelveien');
        $browser->visit('/search')->waitFor('#search-collection')->assertSeeIn('#search-collection', 'Building → Contracts → Eksempelveien');
    });
});

it('uses one library type and view control and keeps mobile file content visible', function (): void {
    $user = $this->createUser();
    $file = File::factory()->create(['user_id' => $user->id, 'status' => 'processing', 'file_type' => 'receipt', 'fileName' => 'Mobile visible scan.pdf']);
    $this->browse(function (Browser $browser) use ($user, $file): void {
        $this->loginAs($browser, $user);
        $browser->resize(390, 844)->visit('/library')->waitFor('@library-query')->assertMissing('[aria-label="Workspace navigation"]')
            ->assertMissing('[aria-label="Smart views"]')->assertMissing('input[aria-label="Search your library"]')
            ->select('select[aria-label="Document type"]', 'receipt')->waitFor('@library-file-'.$file->id)
            ->select('select[aria-label="Library view"]', 'processing')->waitForText('In progress')->assertSelected('select[aria-label="Document type"]', 'receipt');
        $browser->assertScript("document.querySelector('[dusk=library-file-".$file->id."]').getBoundingClientRect().bottom < window.innerHeight", true);
        $browser->press('Filters')->waitFor('#library-more-filters')->select('select[aria-label="Document type"]', 'invoice')->waitForText('No files match these filters');
    });
});

it('identifies library scans by extracted titles while retaining readable filenames and unfinished identities', function (): void {
    $user = $this->createUser();
    $file = File::factory()->create(['user_id' => $user->id, 'fileName' => 'Scan 16 Feb 2025 at 14.53 distinguishing suffix.pdf', 'status' => 'completed']);
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'from_name' => 'Identifiable vendor', 'invoice_date' => '2026-02-10', 'total_amount' => 21.56, 'currency' => 'EUR']);
    ExtractableEntity::create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'invoice', 'entity_id' => $invoice->id, 'is_primary' => true, 'extracted_at' => now()]);
    foreach (['processing', 'failed'] as $status) {
        File::factory()->create(['user_id' => $user->id, 'fileName' => $status.' scan.pdf', 'status' => $status]);
    }
    $this->browse(function (Browser $browser) use ($user, $file): void {
        $this->loginAs($browser, $user);
        $browser->resize(390, 844)->visit('/library')->waitFor('@library-file-'.$file->id)->assertSee('Identifiable vendor')
            ->assertSee('distinguishing suffix.pdf')->assertSee('processing scan.pdf')->assertSee('failed scan.pdf')->assertSee('21.56');
        $browser->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    });
});

it('applies real search range and folder filters with the collection engine in the browser', function (): void {
    $user = $this->createUser();
    $folder = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Filtered folder']);
    $file = File::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'file_type' => 'receipt']);
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Range matched merchant']);
    Receipt::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'merchant_id' => $merchant->id, 'receipt_date' => '2026-02-10', 'total_amount' => 20]);
    $file->collections()->attach($folder);
    $this->browse(function (Browser $browser) use ($user, $folder): void {
        $this->loginAs($browser, $user);
        $browser->resize(1440, 1000)->visit('/search?type=receipt&date_from=2026-02-01&date_to=2026-02-28&amount_min=10&amount_max=20&collection_id='.$folder->id)
            ->waitForText('Range matched merchant')->assertSelected('#search-collection', (string) $folder->id)
            ->type('#search-amount-max', '15')->waitForText('No results found')->assertDontSee('Range matched merchant');
    });
});

it('finds activity by filename and reviews compact failed rows with per-file actions', function (): void {
    $user = $this->createUser();
    $first = File::factory()->create(['user_id' => $user->id, 'status' => 'failed', 'fileName' => 'A batch scan.pdf']);
    $second = File::factory()->create(['user_id' => $user->id, 'status' => 'failed', 'fileName' => 'B target scan.pdf']);
    File::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'fileName' => 'Completed scan.pdf']);
    $this->browse(function (Browser $browser) use ($user, $first, $second): void {
        $this->loginAs($browser, $user);
        $browser->visit('/files-processing')->waitFor('@activity-query')->select('select[aria-label="Processing status"]', 'failed')
            ->select('select[aria-label="Sort activity"]', 'name')->waitForText('A batch scan.pdf')->waitUntilMissing('a[title="Completed scan.pdf"]');
        $browser->assertScript("document.querySelector('[dusk=activity-file-".$first->id."]').getBoundingClientRect().height < 140", true);
        $browser->resize(390, 844)->type('@activity-query', 'target')->waitUntilMissing('@activity-file-'.$first->id)
            ->assertPresent('@activity-file-'.$second->id)->press('Show details')->assertPresent('button.workspace-primary')->assertSee('Extraction report');
        $browser->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
        $browser->press('Hide details')->waitUntilMissing('#activity-details-'.$second->id)->type('@activity-query', 'missing')->waitForText('No files yet');
    });
});

it('uses selected dates and source currency consistently in invoice listings search details and notifications', function (string $language, string $dateFormat, string $date, string $amount): void {
    $user = $this->createUser();
    UserPreference::updateOrCreate(['user_id' => $user->id], [
        'language' => $language, 'date_format' => $dateFormat, 'currency' => 'NOK', 'timezone' => 'UTC',
    ]);
    $invoice = Invoice::factory()->for(File::factory()->for($user))->create([
        'user_id' => $user->id, 'invoice_date' => '2026-06-01', 'total_amount' => '1234.56',
        'currency' => 'USD', 'invoice_number' => 'LOCALE-CHECK', 'from_name' => 'Locale supplier',
    ]);
    $user->notifications()->create([
        'id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\ReceiptProcessed',
        'data' => ['type' => 'receipt_processed', 'merchant_name' => 'Locale supplier', 'amount' => '1234.56', 'currency' => 'USD'],
        'created_at' => '2026-06-01 00:00:00',
    ]);
    $this->browse(function (Browser $browser) use ($user, $invoice, $date, $amount): void {
        $browser->loginAs($user);
        foreach (['/invoices', '/search?query=LOCALE-CHECK&type=invoice', '/invoices/'.$invoice->id] as $path) {
            $browser->visit($path)->waitForText('Locale supplier')->assertSee($date)->assertSee($amount);
        }
        $browser->click('button[aria-label="View notifications"]')->waitFor('[role="menu"]')
            ->assertSeeIn('[role="menu"]', $date)->assertSeeIn('[role="menu"]', $amount);
    });
    expect($invoice->fresh()->total_amount)->toBe('1234.56')->and($invoice->fresh()->currency)->toBe('USD');
})->with([
    ['en', 'Y-m-d', '2026-06-01', '$1,234.56'],
    ['nb', 'd.m.Y', '01.06.2026', '1 234,56 USD'],
]);
