<?php

use App\Models\Category;
use App\Models\Collection;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Receipt;
use Laravel\Dusk\Browser;

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
