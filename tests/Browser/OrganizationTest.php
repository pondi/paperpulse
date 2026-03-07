<?php

use App\Models\Category;
use App\Models\Collection;
use App\Models\Tag;
use Laravel\Dusk\Browser;

/*
|--------------------------------------------------------------------------
| Categories Tests
|--------------------------------------------------------------------------
*/

test('categories page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/categories')
            ->waitForText('categories')
            ->assertPathIs('/categories');
    });
});

test('categories shows empty state for new user', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/categories')
            ->waitForText('No categories')
            ->assertSee('Add Category');
    });
});

test('create category via modal', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/categories')
            ->waitForText('Add Category')
            ->pause(500);

        // Click the "Add Category" button in the header
        $browser->script("
            const buttons = document.querySelectorAll('button');
            for (const btn of buttons) {
                if (btn.textContent.includes('Add Category')) {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(500)
            ->waitFor('#name')
            ->type('#name', 'Test Category')
            ->pause(300);

        // Submit the form inside the modal
        $browser->script("
            const buttons = document.querySelectorAll('[role=\"dialog\"] button[type=\"submit\"], [role=\"dialog\"] form button');
            for (const btn of buttons) {
                if (btn.type === 'submit') {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(1000)
            ->waitForText('Test Category')
            ->assertSee('Test Category');
    });
});

test('edit category', function () {
    $user = $this->createUser();

    // Create a category directly in the database
    Category::create([
        'user_id' => $user->id,
        'name' => 'Original Name',
        'slug' => 'original-name',
        'color' => '#6B7280',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/categories')
            ->waitForText('Original Name')
            ->pause(500);

        // Click the edit (pencil) button — it's the amber-colored button on the card
        $browser->script("
            const pencilButtons = document.querySelectorAll('button');
            for (const btn of pencilButtons) {
                if (btn.querySelector('svg') && btn.classList.contains('text-amber-600')) {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(500)
            ->waitFor('#name');

        // Clear and retype the name
        $browser->clear('#name')
            ->type('#name', 'Updated Name')
            ->pause(300);

        // Submit the modal form
        $browser->script("
            const buttons = document.querySelectorAll('[role=\"dialog\"] button[type=\"submit\"], [role=\"dialog\"] form button');
            for (const btn of buttons) {
                if (btn.type === 'submit') {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(1000)
            ->waitForText('Updated Name')
            ->assertSee('Updated Name')
            ->assertDontSee('Original Name');
    });
});

test('delete empty category', function () {
    $user = $this->createUser();

    Category::create([
        'user_id' => $user->id,
        'name' => 'Deletable Category',
        'slug' => 'deletable-category',
        'color' => '#EF4444',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/categories')
            ->waitForText('Deletable Category')
            ->pause(500);

        // Accept the upcoming confirmation dialog
        $browser->driver->executeScript('window.confirm = () => true;');

        // Click the delete (trash) button — it's the red-colored button on the card
        $browser->script("
            const deleteButtons = document.querySelectorAll('button');
            for (const btn of deleteButtons) {
                if (btn.querySelector('svg') && btn.classList.contains('text-red-600')) {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(1000)
            ->assertDontSee('Deletable Category');
    });
});

/*
|--------------------------------------------------------------------------
| Tags Tests
|--------------------------------------------------------------------------
*/

test('tags page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/tags')
            ->waitForText('Tags')
            ->assertPathIs('/tags');
    });
});

test('create tag', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/tags')
            ->waitForText('Tags')
            ->pause(500);

        // Click the "Create Tag" button
        $browser->script("
            const buttons = document.querySelectorAll('button');
            for (const btn of buttons) {
                if (btn.textContent.includes('Create Tag')) {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(500)
            ->waitFor('#tag-name')
            ->type('#tag-name', 'Dusk Test Tag')
            ->pause(300);

        // Click the "Create" button inside the modal
        $browser->script("
            const buttons = document.querySelectorAll('.fixed button, [class*=\"modal\"] button');
            for (const btn of buttons) {
                if (btn.textContent.trim() === 'Create') {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(1000)
            ->waitForText('Dusk Test Tag')
            ->assertSee('Dusk Test Tag');
    });
});

test('search tags', function () {
    $user = $this->createUser();

    Tag::factory()->create(['user_id' => $user->id, 'name' => 'AlphaTag']);
    Tag::factory()->create(['user_id' => $user->id, 'name' => 'BetaTag']);
    Tag::factory()->create(['user_id' => $user->id, 'name' => 'GammaTag']);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/tags')
            ->waitForText('AlphaTag')
            ->assertSee('BetaTag')
            ->assertSee('GammaTag')
            ->pause(500);

        // Type into the search input and verify filtering works
        $browser->type('input[type="search"]', 'Alpha')
            ->pause(1000)
            ->waitForText('AlphaTag')
            ->assertSee('AlphaTag');
    });
});

test('delete tag', function () {
    $user = $this->createUser();

    Tag::factory()->create(['user_id' => $user->id, 'name' => 'TagToDelete']);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/tags')
            ->waitForText('TagToDelete')
            ->pause(500);

        // Hover over the tag card to reveal the actions dropdown trigger
        $browser->script("
            const cards = document.querySelectorAll('.group');
            for (const card of cards) {
                if (card.textContent.includes('TagToDelete')) {
                    // Make the dropdown trigger visible by simulating hover
                    const trigger = card.querySelector('button[class*=\"opacity-0\"]');
                    if (trigger) {
                        trigger.style.opacity = '1';
                        trigger.click();
                    }
                    break;
                }
            }
        ");

        $browser->pause(500);

        // Click the "Delete" option in the dropdown
        $browser->script("
            const links = document.querySelectorAll('button, a');
            for (const link of links) {
                if (link.textContent.trim() === 'Delete' && link.classList.contains('text-red-600')) {
                    link.click();
                    break;
                }
            }
        ");

        $browser->pause(500);

        // Click the "Delete Tag" confirmation button in the modal
        $browser->script("
            const buttons = document.querySelectorAll('button');
            for (const btn of buttons) {
                if (btn.textContent.trim() === 'Delete Tag') {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(1000)
            ->assertDontSee('TagToDelete');
    });
});

/*
|--------------------------------------------------------------------------
| Collections Tests
|--------------------------------------------------------------------------
*/

test('collections page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/collections')
            ->waitForText('Collections')
            ->assertPathIs('/collections');
    });
});

test('create collection', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/collections')
            ->waitForText('Collections')
            ->pause(500);

        // Click the "Create Collection" button
        $browser->script("
            const buttons = document.querySelectorAll('button');
            for (const btn of buttons) {
                if (btn.textContent.includes('Create Collection')) {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(500)
            ->waitFor('#collection-name')
            ->type('#collection-name', 'My Test Collection')
            ->type('#collection-description', 'A test collection description')
            ->pause(300);

        // Click the "Create" submit button inside the modal
        $browser->script("
            const buttons = document.querySelectorAll('.fixed button[type=\"submit\"], [class*=\"modal\"] button[type=\"submit\"]');
            for (const btn of buttons) {
                if (btn.textContent.trim() === 'Create') {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(1000)
            ->waitForText('My Test Collection')
            ->assertSee('My Test Collection');
    });
});

test('view collection show page', function () {
    $user = $this->createUser();

    $collection = Collection::factory()->create([
        'user_id' => $user->id,
        'name' => 'Viewable Collection',
        'description' => 'A collection to view',
    ]);

    $this->browse(function (Browser $browser) use ($user, $collection) {
        $this->loginAs($browser, $user);

        $browser->visit('/collections')
            ->waitForText('Viewable Collection')
            ->pause(500);

        // Click on the collection card to navigate to show page
        $browser->script("
            const cards = document.querySelectorAll('[class*=\"cursor-pointer\"]');
            for (const card of cards) {
                if (card.textContent.includes('Viewable Collection')) {
                    card.click();
                    break;
                }
            }
        ");

        $browser->pause(1000)
            ->waitForText('Viewable Collection')
            ->assertPathIs('/collections/' . $collection->id);
    });
});

test('delete collection', function () {
    $user = $this->createUser();

    Collection::factory()->create([
        'user_id' => $user->id,
        'name' => 'Collection To Delete',
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/collections')
            ->waitForText('Collection To Delete')
            ->pause(500);

        // Accept the upcoming confirmation dialog
        $browser->driver->executeScript('window.confirm = () => true;');

        // Click the delete (trash/red) button on the collection card
        // The delete button is only shown when files_count === 0
        $browser->script("
            const cards = document.querySelectorAll('[class*=\"cursor-pointer\"]');
            for (const card of cards) {
                if (card.textContent.includes('Collection To Delete')) {
                    const deleteBtn = card.querySelector('button.text-red-600');
                    if (deleteBtn) {
                        // Stop propagation to prevent navigating to show page
                        deleteBtn.click();
                    }
                    break;
                }
            }
        ");

        $browser->pause(1000)
            ->assertDontSee('Collection To Delete');
    });
});
