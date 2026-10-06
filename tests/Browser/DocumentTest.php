<?php

use App\Models\BankStatement;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Invoice;
use App\Models\ReturnPolicy;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\Files\FileUploadConfigService;
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
            ->click('div.flex.items-center.space-x-2 > button:last-child')->waitFor('table')
            ->assertPresent('tbody a[download="Invoice.pdf"]')
            ->click('tbody tr td:nth-child(3) button')->waitFor('div.fixed.right-0')
            ->waitFor('div.fixed.right-0 button[aria-haspopup="menu"]')
            ->waitUntil("document.querySelector('div.fixed.right-0 button[aria-haspopup=menu]').getBoundingClientRect().width > 0 && document.querySelector('div.fixed.right-0 button[aria-haspopup=menu]').getBoundingClientRect().right <= innerWidth")
            ->click('div.fixed.right-0 button[aria-haspopup="menu"]')->waitFor('div.fixed.right-0 a[download]')
            ->assertScript("document.querySelector('div.fixed.right-0 a[download]').href.includes('/documents/serve?guid=')", true);
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
            ->click('div.flex.items-center.space-x-2 > button:last-child')->waitFor('nav.relative span[aria-disabled="true"]')
            ->assertMissing('nav a[aria-disabled="true"]')
            ->click('nav.relative a[href*="page=2"]')->waitUntil("location.search === '?page=2'")
            ->click('div.flex.items-center.space-x-2 > button:last-child')->waitFor('nav.relative span[aria-disabled="true"]');
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
            ->assertSee('Filters');

        // Both grid and list toggle buttons should be present (SVG icon buttons)
        $browser->assertPresent('button svg');
    });
});
