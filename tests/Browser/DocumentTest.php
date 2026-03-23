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
use Laravel\Dusk\Browser;

test('upload page loads', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents/upload')
            ->waitForText('Upload Your Documents')
            ->assertSee('Upload Your Documents')
            ->assertSee('Upload files')
            ->assertSee('or drag and drop')
            ->assertPresent('input[type="file"]');
    });
});

test('file type toggle works', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents/upload')
            ->waitForText('Upload Your Documents');

        // Receipt is active by default — verify hint text for receipts
        $browser->assertSee('PDF, PNG, JPG up to 10MB');

        // Click Document toggle button (not a link — it's a <button>)
        $browser->click('button[class*="rounded-r-lg"]')
            ->pause(300);

        // Document hint text should appear with expanded file types
        $browser->assertSee('PDF, PNG, JPG, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV up to 50MB');

        // Click Receipt toggle back
        $browser->click('button[class*="rounded-l-lg"]')
            ->pause(300);

        // Receipt hint text should return
        $browser->assertSee('PDF, PNG, JPG up to 10MB');
    });
});

test('can attach file for upload', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents/upload')
            ->waitForText('Upload Your Documents')
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
            ->waitForText('Upload Your Documents')
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
            ->waitForText('Upload Your Documents')
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
