<?php

use App\Models\ArchiveExport;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\OrganizationRun;
use App\Models\Receipt;
use App\Models\Tag;
use App\Models\UserPreference;
use App\Models\Vendor;
use App\Services\Files\StoragePathBuilder;
use Dompdf\Dompdf;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Smalot\PdfParser\Parser;

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
        $browser->loginAs($user)->visit('/collections/recommendations')->waitForText($message)->assertSee('Archive folder review');
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
            ->click('div.flex.items-center.space-x-2 > button:last-child')->waitFor('table')
            ->assertPresent('thead input[aria-label="Select all documents on this page"]')
            ->check('thead input[type="checkbox"]')->assertChecked('tbody input[type="checkbox"]')
            ->visit('/search?query=Same%20store&type=receipt')->waitFor('input[aria-label^="Select receipt"]')
            ->assertPresent('input[aria-label="Select all search results on this page"]')
            ->assertScript("Array.from(document.querySelectorAll('a[target=_blank]')).filter(a => a.title === 'Open in new tab').every(a => a.getAttribute('aria-label')?.includes('Same store'))", true);
    });
});

it('opens useful vendor details through the menu and direct links with working back navigation', function (): void {
    $user = $this->createUser();
    $vendor = Vendor::create(['user_id' => $user->id, 'name' => 'DigitalOcean', 'description' => 'Cloud hosting', 'contact_email' => 'support@example.test']);
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id, 'currency' => 'NOK']);
    LineItem::create(['receipt_id' => $receipt->id, 'vendor_id' => $vendor->id, 'text' => 'Cloud subscription', 'qty' => 1, 'price' => 100]);
    $this->browse(function (Browser $browser) use ($user, $vendor): void {
        $browser->loginAs($user)->visit('/vendors')->waitForText('DigitalOcean')
            ->click('li button[aria-haspopup="menu"]')->click('a[href$="/vendors/'.$vendor->id.'"]')
            ->waitForText('Vendor details')->assertSee('Cloud hosting')->assertSee('support@example.test')
            ->assertSee('Cloud subscription')->clickLink('All vendors')->waitForLocation('/vendors')
            ->back()->waitForLocation('/vendors/'.$vendor->id)->assertSee('Cloud subscription')
            ->visit('/vendors/'.$vendor->id)->waitForText('Purchased items (1)');
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

it('keeps failed file recovery actions visible on narrow activity pages', function (): void {
    $user = $this->createUser();
    File::factory()->create(['user_id' => $user->id, 'status' => 'failed', 'fileName' => str_repeat('Long filename ', 15).'.pdf']);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/files-processing?status=failed')->waitForText('CHANGE TYPE & RETRY');
        foreach ([320, 390] as $width) {
            $browser->resize($width, 844)->assertSee('RETRY PROCESSING')->assertSee('CHANGE TYPE & RETRY');
            $browser->assertScript("Array.from(document.querySelectorAll('button')).filter(el => /Retry Processing|Change Type & Retry/.test(el.textContent)).every(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; })", true);
            $browser->press('CHANGE TYPE & RETRY')->waitForText('APPLY & RETRY');
            $browser->assertScript("Array.from(document.querySelectorAll('button')).find(el => el.textContent.includes('Apply & Retry')).getBoundingClientRect().right <= innerWidth", true);
            $browser->press('CHANGE TYPE & RETRY');
        }
    });
});

it('renders document metadata and navigation with populated and nullable dates', function (): void {
    $user = $this->createUser();

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
                ->waitForText('Electrical plan')->assertSee('File Information')
                ->assertSee('Last Updated in PaperPulse')->assertPresent('a[href$="/documents"]');
            $browser->assertScript("Array.from(document.querySelectorAll('dt')).find(el => el.textContent === 'Last Updated in PaperPulse').nextElementSibling.textContent.trim()", $date ?? '');
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
            $browser->assertSee('Edit Collection')->assertSee('Review recommendations');
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
            ->assertPresent('button[aria-label="Edit Named folder"]')
            ->assertPresent('button[aria-label="Archive Named folder"]')
            ->assertPresent('button[aria-label="Delete Named folder"]')
            ->visit('/collections?archived=1')->waitForText('Archived folder')
            ->assertPresent('button[aria-label="Unarchive Archived folder"]')
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
            $browser->resize($width, 844);
            $browser->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
            $browser->assertScript(<<<'JS'
                (() => {
                    const controls = Array.from(document.querySelectorAll('button[aria-label$="mobile management"]'));
                    return controls.length === 3 && controls.every((button, index) => {
                        const target = button.getBoundingClientRect();
                        const icon = button.querySelector('svg').getBoundingClientRect();
                        return target.width >= 44 && target.height >= 44 && target.right <= window.innerWidth
                            && Math.abs((target.left + target.right) / 2 - (icon.left + icon.right) / 2) < 1
                            && (!index || controls[index - 1].getBoundingClientRect().right < target.left);
                    });
                })()
                JS, true);
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
        $browser->loginAs($user)->visit('/search?query=DigitalOcean&type=invoice')->waitForText('1,234.56 USD');

        foreach ([320, 390, 1440] as $width) {
            $browser->resize($width, 844)->assertSee('DigitalOcean with a longer merchant identity');
            $browser->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
            $browser->assertScript(<<<'JS'
                (() => {
                    const title = document.querySelector('h3[ class*="text-base"]');
                    const heading = title.parentElement.parentElement;
                    const date = heading.querySelector('span.text-xs.text-zinc-500').getBoundingClientRect();
                    const amount = heading.querySelector('.text-lg.font-bold').getBoundingClientRect();
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

        $browser->assertSeeIn('label[for="search-sort"]', 'Sort by:')
            ->assertScript("document.getElementById('search-sort').labels[0].textContent.trim()", 'Sort by:')
            ->type('#search-amount-min', '0')->type('#search-amount-max', '100');

        foreach ($labels as $id => $label) {
            $browser->assertSeeIn('label[for="'.$id.'"]', $label);
            $browser->assertScript("document.getElementById('$id').labels[0].textContent.trim()", $label);
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
            ->press('Clear filters (keep search)')->waitForText('Found 1 result')
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
            ->waitFor('.relative.ml-3 > div > button')->click('.relative.ml-3 > div > button')
            ->waitFor('[role="menu"] a[href$="/preferences"]');

        foreach ([320, 390] as $width) {
            $browser->resize($width, 844);
            $browser->script('const bell = document.querySelector(".relative.ml-3"); bell.style.position = "absolute"; bell.style.left = "80px";');

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
        $trigger = '.relative.ml-3 > div > button';
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
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create([
        'user_id' => $user->id,
        'total_amount' => 42,
        'receipt_date' => '2026-10-05',
        'currency' => 'NOK',
        'receipt_description' => 'Persisted description',
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt): void {
        $browser->loginAs($user)->visit('/receipts/'.$receipt->id)->waitForText('Persisted description')
            ->press('Edit Receipt')->press('Cancel')->waitForText('Edit Receipt')
            ->press('Edit Receipt')->type('dl input[type="number"]', '99')
            ->press('Cancel')->waitForText('Edit Receipt');
        expect($receipt->fresh()->total_amount)->toEqual(42);
        $browser->press('Edit Receipt')->assertInputValue('dl input[type="number"]', '42.00')
            ->type('dl input[type="number"]', '55')->press('Save Changes')->waitForText('Edit Receipt');
    });

    expect($receipt->fresh()->total_amount)->toEqual(55);
});

it('wraps long receipt descriptions and preserves multiline notes while editing', function (): void {
    $user = $this->createUser();
    $description = str_repeat('A readable sentence describing the receipt. ', 5);
    $note = "First line\nSecond line\nThird line";
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create([
        'user_id' => $user->id, 'receipt_description' => $description, 'note' => $note,
        'receipt_date' => '2026-10-05', 'currency' => 'NOK', 'total_amount' => 42,
    ]);

    $this->browse(function (Browser $browser) use ($user, $receipt, $description, $note): void {
        $browser->loginAs($user)->visit('/receipts/'.$receipt->id)->waitForText('Edit Receipt')->press('Edit Receipt')
            ->assertInputValue('textarea[aria-label="Description"]', $description)
            ->assertInputValue('dl > div:last-child textarea', $note);
        foreach ([320, 390, 1440] as $width) {
            $browser->resize($width, 844)->assertScript(<<<'JS'
                Array.from(document.querySelectorAll('dl textarea')).every(el =>
                    el.rows >= 4 && el.scrollWidth <= el.clientWidth && getComputedStyle(el).whiteSpace === 'pre-wrap')
                JS, true);
        }
        $browser->type('dl > div:last-child textarea', $note."\nSaved fourth line")
            ->press('Save Changes')->waitForText('Edit Receipt')
            ->refresh()->waitForText('Saved fourth line')->press('Edit Receipt')
            ->assertInputValue('dl > div:last-child textarea', $note."\nSaved fourth line");
    });
});

it('explains upload modes and updates supported formats when switching modes', function (): void {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/documents/upload')->waitForText('Receipt mode accepts')
            ->assertAttribute('button[aria-pressed="true"]', 'aria-pressed', 'true')
            ->assertSee('Automatic classification determines the final type');
        $browser->assertScript("document.querySelector('input[type=file]').accept.includes('.docx')", false);
        $browser->attach('input[type=file]', base_path('tests/Browser/fixtures/test-image.jpg'))
            ->waitForText('test-image.jpg')->press('Document')
            ->waitForText('Document mode accepts invoices, contracts, bank statements')
            ->assertDontSee('test-image.jpg');
        $browser->assertScript("document.querySelector('input[type=file]').accept.includes('.docx')", true);
        $browser->press('Receipt')->waitForText('Receipt mode accepts');
        $browser->assertScript("document.querySelector('input[type=file]').accept.includes('.docx')", false);
    });
});

it('keeps shared upload pickers readable in both themes through focus selection and menus', function (): void {
    $user = $this->createUser();
    Collection::factory()->create(['user_id' => $user->id, 'name' => 'Picker folder', 'color' => '#18181b']);
    Tag::factory()->create(['user_id' => $user->id, 'name' => 'Picker tag', 'color' => '#18181b']);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/documents/upload')->waitFor('input[placeholder="Search or create collections..."]');
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
        $browser->loginAs($user)->visit('/exports')->waitForText('No exports available.')
            ->assertSee('select the receipts using their checkboxes')
            ->clickLink('Select receipts to export')->waitForLocation('/receipts')
            ->check('thead input[type="checkbox"]')->waitForText('Export')->press('Export')
            ->waitForText('Export as CSV')->assertSee('Export as PDF');

        ArchiveExport::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
        ArchiveExport::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'expires_at' => now()->subMinute()]);
        $browser->visit('/exports')->waitForText('PDF export · pending')
            ->assertSee('0 / 100 processed')->assertSee('PDF export · expired')
            ->assertSee('This download has expired')->assertDontSee('No exports available.')
            ->assertMissing('a[href*="/download"]');
    });
});

it('sets distinct browser titles with exactly one product suffix', function (): void {
    $user = $this->createUser();
    $titles = [
        '/analytics' => 'Reports',
        '/invoices' => 'Invoices',
        '/contracts' => 'Contracts',
        '/bank-statements' => 'Bank Statements',
        '/vouchers' => 'Vouchers',
        '/documents/upload' => 'Upload files',
        '/pulsedav' => 'Scanner imports',
        '/scanner' => 'Scan a document',
    ];

    $this->browse(function (Browser $browser) use ($user, $titles): void {
        $browser->loginAs($user);
        foreach ($titles as $url => $title) {
            $browser->visit($url)->waitUntil('document.title === '.json_encode($title.' - PaperPulse'))
                ->assertTitle($title.' - PaperPulse');
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
    $file = File::factory()->for($user)->create();
    $receipt = Receipt::factory()->for($file)->create(['user_id' => $user->id]);
    $collection = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Needs review']);
    $file->collections()->attach($collection);

    $this->browse(function (Browser $browser) use ($user, $receipt, $file, $collection): void {
        $browser->loginAs($user)->visit('/receipts/'.$receipt->id)->waitForText('Needs review')
            ->assertSee('No tags added.')->assertSee('Folder changes save immediately.');
        $membershipCount = <<<'JS'
            (() => {
                const panel = Array.from(document.querySelectorAll('h3')).find(el => el.textContent.trim() === 'Collections').parentElement;
                return (panel.textContent.match(/Needs review/g) || []).length;
            })()
            JS;
        $browser->assertScript($membershipCount, 1)
            ->press('Edit Receipt')->waitForText('Save Changes')->assertScript($membershipCount, 1)
            ->press('Cancel')->waitForText('Edit Receipt')->assertScript($membershipCount, 1);
        $file->collections()->detach($collection);
        $browser->refresh()->waitForText('Not assigned to any collections')->assertSee('No tags added.');
    });
});

it('identifies the admin processing analytics browser page', function (): void {
    $user = $this->createUser(['is_admin' => true]);
    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/analytics/processing')->waitForText('AI Processing Analytics')
            ->assertTitleContains('AI Processing Analytics');
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
                return counts['Total Files'] === 5 && counts.Completed === 1 && counts['In Progress'] === 2
                    && counts.Failed === 1 && counts['Needs review'] === 1;
            })()
            JS, true);
    });
});

it('explains empty direct folder contents and links to populated subfolders', function (): void {
    $user = $this->createUser();
    $parent = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Building']);
    $child = Collection::factory()->create(['user_id' => $user->id, 'parent_id' => $parent->id, 'name' => 'Contracts']);
    $file = File::factory()->create(['user_id' => $user->id, 'fileName' => 'contract.pdf', 'status' => 'completed']);
    $child->files()->attach($file);

    $this->browse(function (Browser $browser) use ($user, $parent, $child, $file): void {
        $browser->loginAs($user)->visit('/collections/'.$parent->id)->waitForText('Building');
        foreach ([390, 1440] as $width) {
            $browser->resize($width, 900)->assertSee('No files directly in this collection')
                ->assertSee('Files in subfolders are listed separately')->assertSee('Upload to this collection')
                ->assertPresent('a[href$="/collections/'.$child->id.'"]');
        }
        $browser->clickLink('Browse Contracts')->waitForLocation('/collections/'.$child->id)->waitForText('contract.pdf')
            ->assertDontSee('No files directly in this collection');
        $child->files()->detach($file);
        $browser->refresh()->waitForText('No files directly in this collection')->assertSee('Upload to this collection');
    });
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
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id, 'category_id' => $category->id, 'receipt_date' => '2026-10-05', 'total_amount' => 42, 'currency' => 'NOK']);
    $this->browse(function (Browser $browser) use ($user, $receipt, $category): void {
        $browser->loginAs($user)->visit('/receipts/'.$receipt->id)->waitForText('Edit Receipt')
            ->press('Edit Receipt')->waitForText('Save Changes')
            ->assertScript("document.querySelector('select').value", (string) $category->id)
            ->assertScript("Array.from(document.querySelector('select').options).map(option => option.textContent.trim())", ['Uncategorized', 'Garden & Plants'])
            ->type('dl input[type="number"]', '43')->press('Save Changes')->waitForText('Receipt updated successfully')
            ->refresh()->waitForText('Edit Receipt')->press('Edit Receipt')->waitForText('Save Changes')
            ->assertScript("document.querySelector('select').value", (string) $category->id)
            ->select('select', 'Uncategorized')->assertScript("document.querySelector('select').selectedIndex", 0)->press('Save Changes')->waitForText('Receipt updated successfully')->refresh()->waitForText('Edit Receipt')
            ->press('Edit Receipt')->assertScript("document.querySelector('select').selectedIndex", 0);
    });
});

it('renders translated expiry scanner and review labels', function (): void {
    $user = $this->createUser();
    $file = File::factory()->create(['user_id' => $user->id, 'status' => 'needs_review']);
    $this->browse(function (Browser $browser) use ($user, $file): void {
        $browser->loginAs($user)->visit('/dashboard')->waitForText('Expiring Vouchers')
            ->assertSee('Ending Warranties')->assertSee('Expiring within 30 days')
            ->assertSee('No vouchers expiring soon.')->assertSee('No warranties ending soon.')
            ->visit('/preferences')->waitForText('Scanner Preferences')
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
            ->assertTitleContains('Eik Senteret')->click('tbody tr td:nth-child(3)')->waitFor('div.fixed.right-0')
            ->assertPathIs($path)->assertSee('Receipts · Eik Senteret')
            ->waitUntil("document.querySelector('div.fixed.right-0 button').getBoundingClientRect().right <= innerWidth")
            ->click('div.fixed.right-0 button')->waitUntilMissing('div.fixed.right-0')->clickLink('All Receipts')->waitForLocation('/receipts');
        $browser->back()->waitForLocation($path)->waitForText('Receipts · Eik Senteret');
    });
});

it('saves receipt folder edits and cancels them without changing saved memberships', function (): void {
    $user = $this->createUser();
    $file = File::factory()->for($user)->create();
    $receipt = Receipt::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'receipt_date' => '2026-10-05', 'total_amount' => 42, 'currency' => 'NOK']);
    $old = Collection::factory()->for($user)->create(['name' => 'Old Folder']);
    $new = Collection::factory()->for($user)->create(['name' => 'New Folder']);
    $file->collections()->attach($old);
    $this->browse(function (Browser $browser) use ($user, $receipt, $file, $old, $new): void {
        $browser->loginAs($user)->visit('/receipts/'.$receipt->id)->waitForText('Old Folder');
        foreach (['Cancel', 'Save Changes'] as $action) {
            $browser->press('Edit Receipt')->waitForText('Save Changes')
                ->click('div:has(> input[placeholder="Add to collections..."]) > span > button')
                ->type('input[placeholder="Add to collections..."]', 'New Folder')->waitForText('New Folder')
                ->click('div.absolute.z-10 div.cursor-pointer:has(> span.flex)')->press($action);
            if ($action === 'Save Changes') {
                $browser->waitForText('Receipt updated successfully');
            } else {
                $browser->waitForText('Edit Receipt');
            }
            expect($file->fresh()->collections->modelKeys())->toBe([$action === 'Cancel' ? $old->id : $new->id]);
            $browser->refresh()->waitForText($action === 'Cancel' ? 'Old Folder' : 'New Folder');
        }
    });
});

it('keeps folder content ahead of collapsed public sharing at desktop and mobile widths', function (): void {
    $user = $this->createUser();
    $folder = Collection::factory()->for($user)->create(['name' => 'Building', 'description' => null]);
    Collection::factory()->for($user)->create(['name' => 'Contracts', 'parent_id' => $folder->id]);
    $this->browse(function (Browser $browser) use ($user, $folder): void {
        foreach ([1440, 390] as $width) {
            $browser->resize($width, 900)->loginAs($user)->visit('/collections/'.$folder->id)->waitForText('Subfolders');
            $browser->assertScript("Array.from(document.querySelectorAll('h3')).find(el => el.textContent === 'Subfolders').getBoundingClientRect().bottom < innerHeight", true)
                ->assertScript("document.querySelector('details').open", false)
                ->click('summary')->assertSee('Public Sharing');
        }
    });
});

it('names the target parent when managing and creating empty subfolders', function (): void {
    $user = $this->createUser();
    $root = Collection::factory()->for($user)->create(['name' => 'Building']);
    $parent = Collection::factory()->for($user)->create(['name' => 'Contracts', 'parent_id' => $root->id]);
    $leaf = Collection::factory()->for($user)->create(['name' => 'Hønsfaret', 'parent_id' => $parent->id]);
    $this->browse(function (Browser $browser) use ($user, $leaf): void {
        $browser->loginAs($user)->visit('/collections/'.$leaf->id)->clickLink('Manage subfolders')
            ->waitForText('No subfolders in Hønsfaret')->assertSee('Building')->assertSee('Contracts')
            ->press('Create Subfolder')->waitForText('Create subfolder in Hønsfaret')
            ->type('#collection-name', 'Plans')->press('Create')->waitUntilMissing('#collection-name')
            ->assertSee('Plans');
        $browser->clickLink('Back to Hønsfaret')->waitForLocation('/collections/'.$leaf->id);
        expect(Collection::where('name', 'Plans')->sole()->parent_id)->toBe($leaf->id);
    });
});

it('reaches common settings through labelled sections on mobile and desktop', function (): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user): void {
        foreach ([1440, 390] as $width) {
            $browser->resize($width, 900)->loginAs($user)->visit('/preferences')->waitFor('nav[aria-label="Settings sections"]');
            foreach (['general', 'display', 'notifications'] as $id) {
                $browser->script('window.scrollTo(0, 0)');
                $browser->click('a[href="#preferences-'.$id.'"]')
                    ->assertScript("document.querySelector('#preferences-".$id."').getBoundingClientRect().top >= 0 && document.querySelector('#preferences-".$id."').getBoundingClientRect().top < 180", true);
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
                        ->assertScript("document.querySelector('iframe').src.endsWith('#navpanes=0&view=Fit')", true)
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
        Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id, 'merchant_id' => $merchant->id,
            'receipt_date' => '2026-10-06', 'total_amount' => $amount, 'currency' => 'EUR', 'receipt_category' => 'Groceries']);
    }
    $this->browse(function (Browser $browser) use ($user): void {
        foreach ([320, 390] as $width) {
            $browser->resize($width, 844)->loginAs($user)->visit('/dashboard')->waitFor('tbody tr')
                ->assertSee('€42.00')->assertSee('€1,234.56')
                ->assertScript("Array.from(document.querySelectorAll('tbody tr td:nth-child(3)')).every(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth && r.width > 0; })", true)
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
                $browser->resize($width, 844)->loginAs($user)->visit($path)->waitForText('Share');
                $browser->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                    ->assertScript(<<<'JS'
                        Array.from(document.querySelectorAll('header button, header a')).filter(el => el.getBoundingClientRect().width).every(el => {
                            const r = el.getBoundingClientRect();
                            return r.left >= 0 && r.right <= innerWidth;
                        })
                        JS, true)
                    ->press('Share')->waitFor('[role="dialog"]')->keys('[role="dialog"] input', '{escape}')
                    ->waitUntilMissing('[role="dialog"] input');
            }
        }
    });
});

it('uses page scrolling for long details and expands tables when hiding the source', function (): void {
    $user = $this->createUser();
    $receipt = Receipt::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id]);
    LineItem::create(['receipt_id' => $receipt->id, 'text' => 'Short receipt item', 'qty' => 1, 'price' => 10, 'total' => 10]);
    $invoice = Invoice::factory()->for(File::factory()->for($user))->create(['user_id' => $user->id]);
    InvoiceLineItem::factory()->count(50)->create(['invoice_id' => $invoice->id, 'description' => 'Long invoice line description']);
    $this->browse(function (Browser $browser) use ($user, $receipt, $invoice): void {
        foreach (['/receipts/'.$receipt->id, '/invoices/'.$invoice->id] as $path) {
            $browser->resize(1440, 900)->loginAs($user)->visit($path)->waitForText('Hide source preview');
            $browser->assertScript(<<<'JS'
                (() => {
                    const table = document.querySelector('table');
                    for (let el = table.parentElement; el && el !== document.body; el = el.parentElement) {
                        if (el.scrollHeight > el.clientHeight && ['auto', 'scroll', 'hidden'].includes(getComputedStyle(el).overflowY)) return false;
                    }
                    return true;
                })()
                JS, true)
                ->press('Hide source preview')->waitForText('Show source preview')
                ->assertScript("document.querySelector('table').parentElement.clientWidth > 950", true);
            $browser->scrollIntoView('table')->keys('div[tabindex="0"]', '{end}');
            $browser->script('window.scrollTo(0, 0)');
            $browser->press('Show source preview')->waitForText('Hide source preview');
        }
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
            ->click('a[href$="/library?type=receipt"]')->waitForLocation('/library')->waitForText('Library uses the view and sort controls below')
            ->click('button[aria-label="Grid view"]')->waitUntil("document.querySelector('button[aria-label=\"Grid view\"]').getAttribute('aria-pressed') === 'true'")
            ->refresh()->waitUntil("document.querySelector('button[aria-label=\"Grid view\"]').getAttribute('aria-pressed') === 'true'")
            ->click('button[aria-label="List view"]')->waitUntil("document.querySelector('button[aria-label=\"List view\"]').getAttribute('aria-pressed') === 'true'");
    });
});
