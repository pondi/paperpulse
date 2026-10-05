<?php

use App\Models\Category;
use App\Models\Collection;
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
