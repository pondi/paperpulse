<?php

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
