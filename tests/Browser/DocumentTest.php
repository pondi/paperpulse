<?php

use App\Models\BankStatement;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Invoice;
use App\Models\ReturnPolicy;
use App\Models\Tag;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\Files\FileUploadConfigService;
use Facebook\WebDriver\WebDriverKeys;
use Laravel\Dusk\Browser;

it('uses source file downloads in mixed document cards rows and drawers', function (): void {
    $user = $this->createUser();
    foreach ([Document::class, Invoice::class] as $model) {
        $file = File::factory()->for($user)->create(['file_type' => 'document', 'status' => 'completed', 'fileExtension' => 'pdf', 'fileName' => class_basename($model).'.pdf']);
        $entity = $model::factory()->create(['user_id' => $user->id, 'file_id' => $file->id]);
        ExtractableEntity::create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => strtolower(class_basename($model)), 'entity_id' => $entity->id, 'is_primary' => true, 'extracted_at' => now()]);
    }
    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)->visit('/documents')->waitFor('a[download="Invoice.pdf"]')
            ->assertScript("Array.from(document.querySelectorAll('a[download]')).every(a => a.href.includes('/documents/serve?guid='))", true)
            ->click('a[download="Invoice.pdf"]')->assertPathIs('/documents')
            ->click('button[aria-label="List view"]')->waitFor('table')
            ->assertPresent('tbody a[download="Invoice.pdf"]')
            ->click('tbody tr td:nth-child(2) button')->waitFor('[role="dialog"]')
            ->waitFor('[role="dialog"] section[aria-label="Document preview"] a')
            ->assertScript("document.querySelector('[role=dialog] section[aria-label=\"Document preview\"] a').href.includes('/documents/serve?guid=')", true);
    });
});

it('renders disabled document pagination without null URL errors', function (): void {
    $user = $this->createUser();
    $documents = [];
    foreach (range(1, 21) as $number) {
        $file = File::factory()->for($user)->create(['file_type' => 'document', 'status' => 'completed']);
        $document = Document::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'title' => 'Document '.$number, 'document_date' => null]);
        ExtractableEntity::create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true, 'extracted_at' => now()]);
        $documents[] = $document;
    }

    $this->browse(function (Browser $browser) use ($user, $documents): void {
        $browser->loginAs($user)->visit('/documents')->waitFor('input[type="checkbox"]')
            ->click('button[aria-label="List view"]')->waitFor('nav[aria-label="Pagination"] span')
            ->assertMissing('nav a[aria-disabled="true"]')
            ->click('nav[aria-label="Pagination"] a[href*="page=2"]')->waitUntil("location.search === '?page=2'")
            ->click('button[aria-label="List view"]')->waitFor('nav[aria-label="Pagination"] span');
        $browser->visit('/documents/'.$documents[0]->id)->waitForText('Document 1');

        $errors = array_filter($browser->driver->manage()->getLog('browser'), fn (array $entry): bool => str_contains($entry['message'], 'toString') || str_contains($entry['message'], '[Vue error]'));
        expect($errors)->toBeEmpty();
    });
});

test('upload page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents/upload')
            ->waitForText('Upload files')
            ->assertSee('Upload files')
            ->assertSee('or drag and drop')
            ->assertPresent('input[type="file"]');
    });
});

test('unified upload accepts supported formats and detects their type automatically', function () {
    $user = $this->createUser();
    $config = app(FileUploadConfigService::class)->getUploadConfig();
    $hint = strtoupper(implode(', ', array_keys($config['capabilities']['document']))).' up to '.$config['maxFileSizeMb']['document'].'MB';

    $this->browse(function (Browser $browser) use ($user, $hint) {
        $this->loginAs($browser, $user);
        $browser->visit('/documents/upload')
            ->waitForText('Upload files')
            ->assertSee($hint)
            ->assertSee('We detect the file type')
            ->assertMissing('[aria-label="Upload mode"]');
    });
});

test('browser can save console screenshot and source artifacts', function (): void {
    $name = 'artifact-permissions';
    $this->browse(function (Browser $browser) use ($name): void {
        $browser->visit('/login')->waitFor('#email');
        $browser->script("console.error('Artifact permission fixture');");
        $browser->storeConsoleLog($name)->screenshot($name)->storeSource($name);
    });

    foreach (['console' => 'log', 'screenshots' => 'png', 'source' => 'txt'] as $directory => $extension) {
        $path = __DIR__.'/'.$directory.'/'.$name.'.'.$extension;
        expect(is_file($path))->toBeTrue($path);
        expect(filesize($path))->toBeGreaterThan(0);
        unlink($path);
    }
});

test('can attach file for upload', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents/upload')
            ->waitForText('Upload files')
            ->attach('input[type="file"]', realpath(__DIR__.'/fixtures/test-receipt.pdf'))
            ->pause(1000)
            ->assertSee('test-receipt.pdf')
            ->assertSee('Upload 1 file');
    });
});

test('can attach image file for upload', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents/upload')
            ->waitForText('Upload files')
            ->attach('input[type="file"]', realpath(__DIR__.'/fixtures/test-image.jpg'))
            ->pause(1000)
            ->assertSee('test-image.jpg')
            ->assertSee('Upload 1 file');
    });
});

test('upload submit button is disabled with no files', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents/upload')
            ->waitForText('Upload files')
            ->assertSee('Upload 0 files')
            ->assertPresent('button[disabled]');
    });
});

test('documents index loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents')
            ->waitForText('Documents')
            ->assertSee('Documents')
            ->assertSee('Upload Document');
    });
});

test('documents index shows empty state when no documents', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents')
            ->waitForText('Documents')
            ->pause(500)
            ->assertSee('No documents found')
            ->assertSee('Upload your first document to get started.');
    });
});

test('documents index shows documents when they exist', function () {
    $user = $this->createUser();

    // Create a completed file with a primary Document entity
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
        'processing_type' => 'document',
        'status' => 'completed',
    ]);

    $document = Document::factory()->create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'title' => 'Test Document Alpha',
    ]);

    ExtractableEntity::create([
        'file_id' => $file->id,
        'user_id' => $user->id,
        'entity_type' => 'document',
        'entity_id' => $document->id,
        'is_primary' => true,
        'extracted_at' => now(),
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents')
            ->waitForText('Documents')
            ->pause(500)
            ->assertSee('Test Document Alpha')
            ->assertDontSee('No documents found');
    });
});

test('documents index shows entity type badges', function () {
    $user = $this->createUser();

    // Helper to create a file + entity + extractable link
    $createEntity = function (string $entityClass, string $type, array $entityAttrs = []) use ($user) {
        $file = File::factory()->create([
            'user_id' => $user->id,
            'file_type' => 'document',
            'processing_type' => $type,
            'status' => 'completed',
        ]);

        $entity = $entityClass::factory()->create(array_merge([
            'file_id' => $file->id,
            'user_id' => $user->id,
        ], $entityAttrs));

        ExtractableEntity::create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'entity_type' => $type,
            'entity_id' => $entity->id,
            'is_primary' => true,
            'extracted_at' => now(),
        ]);

        return $entity;
    };

    $createEntity(Document::class, 'document', ['title' => 'Badge Test Doc']);
    $createEntity(Invoice::class, 'invoice', ['from_name' => 'Acme Corp']);
    $createEntity(Contract::class, 'contract', ['contract_title' => 'Service Agreement']);
    $createEntity(Voucher::class, 'voucher', ['code' => 'HOLIDAY-2026']);
    $createEntity(Warranty::class, 'warranty', ['product_name' => 'Laptop Pro']);
    $createEntity(ReturnPolicy::class, 'return_policy');
    $createEntity(BankStatement::class, 'bank_statement', ['bank_name' => 'First Bank']);

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents')
            ->waitForText('Documents')
            ->pause(500)
            ->assertDontSee('No documents found')
            ->assertSee('Document')
            ->assertSee('Invoice')
            ->assertSee('Contract')
            ->assertSee('Voucher')
            ->assertSee('Warranty')
            ->assertSee('Statement');
    });
});

test('search input is visible on documents index', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents')
            ->waitForText('Documents')
            ->assertPresent('input[type="search"]');
    });
});

test('view mode toggle exists on documents index', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents')
            ->waitForText('Documents')
            ->assertSee('Search filenames')->assertSee('Category')->assertSee('Tag');

        $browser->assertPresent('button[aria-label="Grid view"][aria-pressed="true"]')
            ->assertPresent('button[aria-label="List view"][aria-pressed="false"]');
    });
});

it('filters document filenames categories and tags and confirms source file deletion', function (): void {
    $user = $this->createUser();
    $category = Category::create(['user_id' => $user->id, 'name' => 'Agreements', 'slug' => 'agreements']);
    $tag = Tag::create(['user_id' => $user->id, 'name' => 'Renewal', 'color' => '#123456']);
    $files = [];
    foreach (range(1, 21) as $number) {
        $file = File::factory()->for($user)->create(['file_type' => 'document', 'status' => 'completed', 'fileName' => 'Scoped agreement '.$number.'.pdf']);
        $document = Document::factory()->for($user)->create(['file_id' => $file->id, 'title' => 'Agreement '.$number, 'category_id' => $category->id, 'summary' => 'Review the renewal terms.']);
        ExtractableEntity::factory()->create(['file_id' => $file->id, 'user_id' => $user->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true]);
        $file->tags()->attach($tag);
        $files[] = $file;
    }
    $this->browse(function (Browser $browser) use ($user, $category): void {
        $browser->resize(1440, 1000)->loginAs($user)->visit('/documents')->waitFor('#document-search')
            ->type('#document-search', 'Scoped')->type('#document-tag', 'Renewal')->select('#document-category', $category->id)
            ->waitUntil("location.search.includes('tag=Renewal')")->click('button[aria-label="List view"]')->waitFor('table')
            ->check('thead input')->assertSee('20 document(s) selected')
            ->assertPresent('[aria-label="Selected document actions"] a[href*="ids"]')
            ->click('nav[aria-label="Pagination"] a[href*="page=2"]')->waitUntil("location.search.includes('page=2')")
            ->waitUntilMissing('[aria-label="Selected document actions"]')->assertInputValue('#document-search', 'Scoped')
            ->assertInputValue('#document-tag', 'Renewal')->assertSelected('#document-category', $category->id)
            ->click('button[aria-label="List view"]')->waitFor('tbody button[dusk]');
        $selected = (int) $browser->attribute('tbody input[type="checkbox"]', 'value');
        $browser->click('tbody button[dusk]')->waitForText('Review the renewal terms.')->press('Edit details')->type('title', 'Unsaved list edit')
            ->click('button[aria-label="Close preview"]')->waitForDialog()->dismissDialog()->assertInputValue('title', 'Unsaved list edit')
            ->press('Cancel editing')->keys('button[aria-label="Close preview"]', WebDriverKeys::ESCAPE)->waitUntilMissing('[role="dialog"]')
            ->waitUntil('document.activeElement.getAttribute("dusk") === "document-preview-'.$selected.'"')
            ->check('thead input')->press('Delete')->waitFor('[role="dialog"]')->press('Cancel')->waitUntilMissing('[role="dialog"]');
        expect(File::find($selected))->not->toBeNull();
        $browser->press('Delete')->waitFor('[role="dialog"]')->click('[role="dialog"] .workspace-danger')->waitUntilMissing('[role="dialog"]');
        expect(File::find($selected))->toBeNull();
        $browser->clickLink('All documents')->waitUntil("location.search === ''")->waitFor('article[data-document-id]')->screenshot('redesign-documents-desktop');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= innerWidth', true)->screenshot('redesign-documents-grid-'.$width)
                ->click('button[aria-label="List view"]')->waitFor('table')->assertScript('document.documentElement.scrollWidth <= innerWidth', true)->screenshot('redesign-documents-list-'.$width)
                ->click('button[aria-label="Grid view"]')->waitFor('article[data-document-id]');
        }
        $browser->script("document.documentElement.classList.add('dark')");
        $browser->screenshot('redesign-documents-dark')->type('#document-search', 'NoMatch')->press('Search')->waitForText('No documents match your filters')->assertSee('All documents');
    });
});

it('keeps shared document browsing read only while allowing search and preview', function (): void {
    $owner = $this->createUser(['name' => 'Document owner']);
    $viewer = $this->createUser();
    $file = File::factory()->for($owner)->create(['file_type' => 'document', 'status' => 'completed', 'fileName' => 'shared-policy.pdf']);
    $document = Document::factory()->for($owner)->create(['file_id' => $file->id, 'title' => 'Shared policy', 'summary' => 'Read the shared policy.']);
    ExtractableEntity::factory()->create(['file_id' => $file->id, 'user_id' => $owner->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true]);
    FileShare::create(['file_id' => $file->id, 'file_type' => 'document', 'shared_by_user_id' => $owner->id, 'shared_with_user_id' => $viewer->id, 'permission' => 'view', 'shared_at' => now()]);
    $this->browse(function (Browser $browser) use ($viewer, $document): void {
        $browser->resize(1440, 1000)->loginAs($viewer)->visit('/documents/shared')->waitForText('Shared policy')->assertSee('Document owner')
            ->assertSee('View only')->assertMissing('input[type="checkbox"]')->type('#shared-document-search', 'Shared')->press('Search')
            ->waitUntil("location.search.includes('search=Shared')")->click('@shared-document-preview-'.$document->id)->waitForText('Read the shared policy.')
            ->assertSee('Shared file · read only')->assertDontSee('Edit details')->click('button[aria-label="Close preview"]')->waitUntilMissing('[role="dialog"]');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->assertScript('document.documentElement.scrollWidth <= innerWidth', true)->screenshot('redesign-shared-documents-'.$width);
        }
        $browser->type('#shared-document-search', 'Absent')->press('Search')->waitForText('No shared documents match your search')->clickLink('Clear search')->waitForText('Shared policy');
    });
});
