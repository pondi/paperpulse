<?php

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Tag;
use Facebook\WebDriver\WebDriverKeys;
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

test('create collection', function (): void {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(320, 844)->loginAs($user)->visit('/collections')->waitForText('No collections')
            ->press('Create Collection')->waitFor('#collection-name')
            ->type('#collection-name', 'My Test Collection')->type('#collection-description', 'A test collection description')
            ->click('button[aria-label="Home"]')->assertAttribute('button[aria-label="Home"]', 'aria-pressed', 'true')
            ->click('button[aria-label="Blue"]')->assertAttribute('button[aria-label="Blue"]', 'aria-pressed', 'true')
            ->assertScript('document.querySelector("[role=dialog]").scrollWidth <= innerWidth', true)
            ->screenshot('redesign-folder-create-mobile')->press('Create')->waitUntilMissing('#collection-name')
            ->waitForText('My Test Collection')->assertSee('0 direct files')->assertSee('No subfolders');
    });
    $collection = Collection::where('user_id', $user->id)->where('name', 'My Test Collection')->firstOrFail();
    expect($collection->icon)->toBe('home')->and($collection->color)->toBe('#3B82F6');
});

test('opens a collection by keyboard and keeps edit actions independent', function (): void {
    $user = $this->createUser();
    $collection = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Viewable Collection']);

    $this->browse(function (Browser $browser) use ($user, $collection): void {
        $browser->resize(1440, 1000)->loginAs($user)->visit('/collections')->waitForText('Viewable Collection');
        $menu = 'button[aria-label="Actions for Viewable Collection"]';
        $browser->keys($menu, WebDriverKeys::ENTER)->waitFor('[role="menu"]')
            ->keys('[role="menu"]', WebDriverKeys::HOME, WebDriverKeys::ENTER)
            ->waitFor('#collection-name')->assertPathIs('/collections')
            ->type('#collection-name', 'Edited Collection')->press('Update')->waitUntilMissing('#collection-name')
            ->waitForText('Edited Collection');
        $browser->assertScript('document.activeElement.getAttribute("aria-label")', 'Actions for Edited Collection');
        $link = 'article a[href$="/collections/'.$collection->id.'"]';
        $browser->keys('button[aria-label="Actions for Edited Collection"]', [WebDriverKeys::SHIFT, WebDriverKeys::TAB]);
        $browser->assertScript('getComputedStyle(document.activeElement).outlineStyle', 'solid')
            ->keys($link, WebDriverKeys::ENTER)->waitForLocation('/collections/'.$collection->id)->assertSee('Edited Collection');
    });
    expect($collection->fresh()->name)->toBe('Edited Collection');
});

test('delete collection', function (): void {
    $user = $this->createUser();
    $collection = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Collection To Delete']);

    $this->browse(function (Browser $browser) use ($user, $collection): void {
        $browser->resize(390, 844)->loginAs($user)->visit('/collections')->waitForText('Collection To Delete')
            ->click('button[aria-label="Actions for Collection To Delete"]')->press('Delete folder')->waitForDialog();
        expect($browser->driver->switchTo()->alert()->getText())->toContain('1 folder(s) and 0 unique file(s)', 'Original files are kept');
        $browser->dismissDialog()->waitFor('button[aria-label="Actions for Collection To Delete"]:not([disabled])')
            ->assertPresent('article[data-folder-id="'.$collection->id.'"]')
            ->click('button[aria-label="Actions for Collection To Delete"]')->press('Delete folder')->waitForDialog()->acceptDialog()
            ->waitUntilMissing('article[data-folder-id="'.$collection->id.'"]')->assertSee('No collections');
    });
    expect($collection->fresh()->trashed())->toBeTrue();
});

test('folder explorer confirms recursive archive and restore', function (): void {
    $user = $this->createUser();
    $parent = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Home records']);
    $child = Collection::factory()->create(['user_id' => $user->id, 'parent_id' => $parent->id, 'name' => 'Insurance']);

    $this->browse(function (Browser $browser) use ($user, $parent, $child): void {
        $browser->resize(390, 844)->loginAs($user)->visit('/collections')->waitForText('Home records')
            ->click('button[aria-label="Actions for Home records"]')->assertDontSee('Delete folder')
            ->press('Archive folder')->waitForDialog();
        expect($browser->driver->switchTo()->alert()->getText())->toContain('2 folder(s)', 'explicitly shared', 'Original files are kept');
        $browser->acceptDialog()->waitUntilMissing('article[data-folder-id="'.$parent->id.'"]');
        expect($parent->fresh()->is_archived)->toBeTrue()->and($child->fresh()->is_archived)->toBeTrue();
        $browser->press('Close')->select('#folder-status', 'true')->waitFor('article[data-folder-id="'.$parent->id.'"]')->assertSee('Archived')
            ->click('button[aria-label="Actions for Home records"]')->press('Restore folder')->waitForDialog()->acceptDialog()
            ->waitForText('Home records restored.')->assertDontSeeIn('article', 'Archived');
        expect($parent->fresh()->is_archived)->toBeFalse()->and($child->fresh()->is_archived)->toBeFalse();
        $browser->press('Close')->click('a[aria-label="Browse 1 subfolders in Home records"]')->waitForText('Subfolders in Home records')
            ->assertSee('Insurance')->assertSee('Back to Home records');
    });
});

test('folder explorer preserves scoped filtering sorting and pagination on narrow screens', function (): void {
    $user = $this->createUser();
    $parent = Collection::factory()->create(['user_id' => $user->id, 'name' => str_repeat('LongFolderName', 7)]);
    Collection::factory()->create(['user_id' => $user->id, 'name' => 'Outside folder']);
    foreach (range(1, 21) as $number) {
        Collection::factory()->create(['user_id' => $user->id, 'parent_id' => $parent->id, 'name' => sprintf('Folder %02d', $number), 'description' => 'Annual archive']);
    }

    $this->browse(function (Browser $browser) use ($user, $parent): void {
        $browser->resize(1440, 1000)->loginAs($user)->visit('/collections?parent_id='.$parent->id)
            ->waitForText('Folder 01')->assertDontSee('Outside folder')->select('#folder-sort', '-name')
            ->waitForText('Folder 21')->type('#folder-search', 'Annual')->waitUntil('new URL(location.href).searchParams.get("search") === "Annual"');
        $browser->waitUntil('document.querySelector("section[aria-label=Folders]").getAttribute("aria-busy") === "false"')
            ->screenshot('redesign-folder-explorer-desktop')->clickLink('Next')->waitForText('Page 2 of 2')
            ->assertSee('Folder 01')->assertInputValue('#folder-search', 'Annual')->assertSelected('#folder-sort', '-name');
        $browser->type('#folder-search', 'Missing folder')->waitForText('No folders match your search')->assertDontSee('No collections')
            ->press('Clear search')->waitForText('Folder 21');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->scrollTo('section[aria-label="Folders"]')->screenshot('redesign-folder-explorer-'.$width);
        }
        $browser->script('document.documentElement.classList.add("dark")');
        $browser->screenshot('redesign-folder-explorer-dark');
        $browser->script('window.scrollTo(0, 0)');
        $browser->press('Create Subfolder')->waitFor('#collection-name')
            ->type('#collection-name', 'New subfolder')->press('Create')->waitUntilMissing('#collection-name')->waitForText('New subfolder');
    });
    expect(Collection::where('user_id', $user->id)->where('name', 'New subfolder')->firstOrFail()->parent_id)->toBe($parent->id);
});

test('collection contents preserve search sort and page context at all screen widths', function (): void {
    $user = $this->createUser();
    $folder = Collection::factory()->for($user)->create(['name' => str_repeat('LongFolderName', 7)]);
    $child = Collection::factory()->for($user)->create(['parent_id' => $folder->id, 'name' => 'Reports']);
    foreach (range(1, 51) as $number) {
        $folder->files()->attach(File::factory()->for($user)->create(['fileName' => sprintf('Report %02d.pdf', $number)])->id);
    }
    $this->browse(function (Browser $browser) use ($user, $folder, $child): void {
        $browser->resize(1440, 1000)->loginAs($user)->visit('/collections/'.$folder->id)->waitFor('#collection-search')
            ->assertSee('Reports')->select('#collection-sort', '-name')->waitForText('Report 51.pdf')
            ->type('#collection-search', 'Report')->press('Search')->waitUntil('new URL(location.href).searchParams.get("search") === "Report"')
            ->waitUntil('document.querySelector("section[aria-label=Subfolders]").getAttribute("aria-busy") === "false"')
            ->screenshot('redesign-contents-desktop');
        $browser->click('section[aria-label="Files in this folder"] nav[aria-label="Pagination"] a[href*="files_page=2"]')
            ->waitUntil('new URL(location.href).searchParams.get("files_page") === "2"')->assertSee('Report 01.pdf')
            ->assertInputValue('#collection-search', 'Report')->assertSelected('#collection-sort', '-name');
        $browser->type('#collection-search', 'Missing')->press('Search')->waitForText('No filenames match your search')
            ->assertSee('No subfolder names match your search.')->press('Clear search')->waitForText('Report 51.pdf');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->screenshot('redesign-contents-'.$width);
        }
        $browser->script('document.documentElement.classList.add("dark")');
        $browser->pause(300)->screenshot('redesign-contents-dark')->click('article a[href$="/collections/'.$child->id.'"]')
            ->waitForLocation('/collections/'.$child->id)->assertSee('No files directly in this collection')
            ->clickLink('Back to '.$folder->name)->waitForLocation('/collections/'.$folder->id);
        $browser->script('document.documentElement.classList.remove("dark")');
    });
});

test('collection editing supports cancel rename errors and validated moves', function (): void {
    $user = $this->createUser();
    $folder = Collection::factory()->for($user)->create(['name' => 'Home records']);
    $child = Collection::factory()->for($user)->create(['name' => 'Insurance', 'parent_id' => $folder->id]);
    Collection::factory()->for($user)->create(['name' => 'Existing folder']);
    $target = Collection::factory()->for($user)->create(['name' => 'Archive']);
    $this->browse(function (Browser $browser) use ($user, $folder, $child, $target): void {
        $browser->resize(320, 844)->loginAs($user)->visit('/collections/'.$folder->id)->waitForText('Edit Collection')
            ->press('Edit Collection')->waitFor('#collection-name')->type('#collection-name', 'Discarded name')
            ->press('Cancel')->waitUntilMissing('#collection-name')->assertSee('Home records');
        expect($folder->fresh()->name)->toBe('Home records');
        $browser->assertScript('document.activeElement.textContent.trim()', 'Edit Collection')
            ->press('Edit Collection')->waitFor('#collection-name')->type('#collection-name', 'Existing folder')
            ->press('Save changes')->waitForText('A folder with this name already exists in this location.')
            ->assertInputValue('#collection-name', 'Existing folder')->type('#collection-name', 'Renamed records')
            ->press('Save changes')->waitUntilMissing('#collection-name')->waitForText('Renamed records');
        $browser->press('Edit Collection')->waitFor('#collection-name')->press('Choose folder')->waitForText('Use this location')
            ->press('Archive →')->waitForText('Up one level')->press('Use this location')->press('Save changes')
            ->waitUntilMissing('#collection-name')->waitForText('Back to Archive');
        expect($folder->fresh()->parent_id)->toBe($target->id)->and($child->fresh()->parent_id)->toBe($folder->id);
        $browser->press('Edit Collection')->waitFor('#collection-name')->press('Choose folder')->waitForText('Use this location')
            ->press('Archive →')->waitForText('Up one level');
        $browser->assertDontSeeIn('[role="dialog"]', 'Renamed records →')->press('Cancel')->press('Cancel')->waitUntilMissing('#collection-name');
        $browser->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
    });
});

test('collection file removal is contextual and keeps the original document', function (): void {
    $user = $this->createUser();
    $folder = Collection::factory()->for($user)->create(['name' => 'Contracts']);
    $file = File::factory()->for($user)->create(['fileName' => 'Agreement.pdf', 'status' => 'completed']);
    $document = Document::factory()->for($user)->create(['file_id' => $file->id, 'title' => 'Service agreement']);
    ExtractableEntity::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_id' => $document->id, 'entity_type' => 'document', 'is_primary' => true]);
    $folder->files()->attach($file);
    $this->browse(function (Browser $browser) use ($user, $folder, $file, $document): void {
        $browser->resize(390, 844)->loginAs($user)->visit('/collections/'.$folder->id)->waitFor('@library-file-'.$file->id)
            ->assertDontSee('Remove from this folder')->keys('@library-file-'.$file->id, WebDriverKeys::ENTER)
            ->waitForText('Document information')->assertSee('The original stays in your library.')
            ->press('Edit details')->waitFor('input[name="title"]')->type('title', 'Unsaved change')
            ->assertSee('Save or cancel your edits first.')->press('Cancel editing')->waitUntilMissing('input[name="title"]')
            ->press('Remove from this folder')->waitForDialog();
        expect($browser->driver->switchTo()->alert()->getText())->toContain('Agreement.pdf', 'Contracts', 'original file');
        $browser->dismissDialog()->assertSee('Remove from this folder')->press('Remove from this folder')->waitForDialog()->acceptDialog()
            ->waitForText('No files directly in this collection')->assertScript('document.activeElement.id', 'collection-search');
        expect($file->fresh()->trashed())->toBeFalse()->and($document->fresh()->title)->toBe('Service agreement');
        $browser->visit('/library')->waitFor('@library-file-'.$file->id)->assertSee('Service agreement');
    });
});

test('shared collection contents hide private descendants and management actions', function (): void {
    $owner = $this->createUser();
    $viewer = $this->createUser();
    $folder = Collection::factory()->for($owner)->create(['name' => 'Shared records']);
    $child = Collection::factory()->for($owner)->create(['parent_id' => $folder->id, 'name' => 'Shared child']);
    Collection::factory()->for($owner)->create(['parent_id' => $folder->id, 'name' => 'Private child']);
    Collection::factory()->for($owner)->create(['parent_id' => $child->id, 'name' => 'Private grandchild']);
    foreach ([$folder, $child] as $shared) {
        CollectionShare::create(['collection_id' => $shared->id, 'shared_by_user_id' => $owner->id, 'shared_with_user_id' => $viewer->id, 'permission' => 'view', 'shared_at' => now()]);
    }
    $file = File::factory()->for($owner)->create(['fileName' => 'Shared file.pdf']);
    $folder->files()->attach($file);
    $this->browse(function (Browser $browser) use ($viewer, $folder, $child, $file): void {
        $browser->resize(390, 844)->loginAs($viewer)->visit('/collections/'.$folder->id)->waitForText('Shared records')
            ->assertSee('Shared child')->assertDontSee('Private child')->assertDontSee('Edit Collection')->assertDontSee('Public sharing')
            ->assertDontSee('Manage subfolders')->assertDisabled('input[aria-label="Select Shared file.pdf"]')
            ->click('@library-file-'.$file->id)->waitForText('Shared file · read only')->assertDontSee('Remove from this folder')
            ->click('button[aria-label="Close preview"]')->waitUntilMissing('aside[aria-label="Selected document"]')
            ->click('article a[href$="/collections/'.$child->id.'"]')->waitForLocation('/collections/'.$child->id)
            ->assertDontSee('Private grandchild')->assertSee('Shared collections');
    });
});

test('collection public links stay secondary and support explicit create cancel and revoke', function (): void {
    $user = $this->createUser();
    $folder = Collection::factory()->for($user)->create(['name' => 'Tax records']);
    $this->browse(function (Browser $browser) use ($user, $folder): void {
        $browser->resize(320, 844)->loginAs($user)->visit('/collections/'.$folder->id)->waitFor('[data-public-sharing]')
            ->assertScript('document.querySelector("[data-public-sharing]").open', false)
            ->click('[data-public-sharing] summary')->press('Create Public Link')->waitFor('#public-link-label')
            ->type('#public-link-label', 'Discarded link')->press('Cancel')->waitUntilMissing('#public-link-label');
        expect($folder->publicLinks()->count())->toBe(0);
        $browser->press('Create Public Link')->waitFor('#public-link-label')->type('#public-link-label', 'Accountant')
            ->type('#public-link-label', str_repeat('x', 256))->press('Create Link')->waitForText('Label cannot exceed 255 characters.')
            ->type('#public-link-label', 'Accountant')->select('#public-link-expiration_preset', '30d')
            ->screenshot('redesign-contents-sharing-mobile')->press('Create Link')->waitForText('Link Created')
            ->press('Done')->waitUntilMissing('[role="dialog"]')->assertSee('Accountant')
            ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
            ->click('button[aria-label="Revoke link"]')->waitForDialog()->acceptDialog()->waitForText('Revoked');
    });
    expect($folder->publicLinks()->sole()->is_active)->toBeFalse();
});
