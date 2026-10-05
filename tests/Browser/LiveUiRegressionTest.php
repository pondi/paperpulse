<?php

use App\Models\Category;
use App\Models\Collection;
use App\Models\Document;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\UserPreference;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;

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
