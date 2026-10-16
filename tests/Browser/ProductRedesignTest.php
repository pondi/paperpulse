<?php

use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Collection;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\Voucher;
use App\Services\Files\StoragePathBuilder;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;

it('previews and edits a document without losing its place or unsaved changes', function (): void {
    $user = $this->createUser();
    $file = File::factory()->for($user)->create(['fileName' => 'Service agreement.pdf', 'file_type' => 'document', 'fileExtension' => 'pdf', 'status' => 'completed']);
    $other = File::factory()->for($user)->create(['fileName' => 'Other document.pdf', 'status' => 'completed']);
    $document = Document::factory()->for($user)->create(['file_id' => $file->id, 'title' => 'Service agreement', 'summary' => 'Design services for the coming year.']);
    ExtractableEntity::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true]);
    $path = StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf');
    Storage::disk('paperpulse')->put($path, conversionPdfFixture());
    $file->update(['s3_original_path' => $path]);

    try {
        $this->browse(function (Browser $browser) use ($user, $file, $other, $document): void {
            $browser->resize(1440, 1000)->loginAs($user)->visit('/library')->waitFor('@library-file-'.$file->id)
                ->assertScript('Array.from(document.querySelectorAll("th")).some(el => el.textContent.trim() === "Tax")', false)
                ->check('input[aria-label="Select Service agreement"]')->assertSee('1 selected')->press('Clear selection')
                ->press('Show review columns')->assertSee('AI confidence')
                ->press('Hide review columns')->click('@library-file-'.$file->id)->waitForText('Design services for the coming year.')
                ->assertPresent('iframe[title="Preview of Service agreement"]')->assertMissing('input[name="title"]')
                ->press('Edit details')->waitFor('input[name="title"]')->type('title', 'Updated service agreement')
                ->click('@library-file-'.$other->id)->waitForDialog()->dismissDialog()
                ->assertInputValue('title', 'Updated service agreement')->press('Save changes')
                ->waitUntilMissing('input[name="title"]')->waitForText('Updated service agreement')
                ->screenshot('redesign-workspace-desktop');
            expect($document->fresh()->title)->toBe('Updated service agreement');
            $browser->click('button[aria-label="Close preview"]')
                ->waitUntilMissing('aside[aria-label="Selected document"]')
                ->assertScript('document.activeElement.getAttribute("dusk")', 'library-file-'.$file->id);
            $browser->keys('@library-file-'.$file->id, WebDriverKeys::ARROW_UP)
                ->waitFor('button[dusk="library-file-'.$other->id.'"][aria-pressed="true"]')
                ->assertScript('document.activeElement.getAttribute("dusk")', 'library-file-'.$other->id)
                ->keys('@library-file-'.$other->id, WebDriverKeys::ARROW_DOWN)
                ->waitFor('button[dusk="library-file-'.$file->id.'"][aria-pressed="true"]')
                ->keys('@library-file-'.$file->id, WebDriverKeys::ESCAPE)
                ->waitUntilMissing('aside[aria-label="Selected document"]');
            foreach ([320, 390, 768] as $width) {
                $browser->resize($width, 844)->click('@library-file-'.$file->id)->waitForText('Document information')
                    ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                    ->screenshot('redesign-workspace-'.$width)
                    ->click('button[aria-label="Close preview"]')->waitUntilMissing('aside[aria-label="Selected document"]');
            }
        });
    } finally {
        Storage::disk('paperpulse')->delete($path);
    }
});

it('keeps preview available but excludes shared read-only files from batch mutation', function (): void {
    $owner = $this->createUser();
    $viewer = $this->createUser();
    $file = File::factory()->for($owner)->create(['fileName' => 'Shared policy.pdf', 'status' => 'completed']);
    $document = Document::factory()->for($owner)->create(['file_id' => $file->id, 'title' => 'Shared policy', 'summary' => 'A policy shared for reading.']);
    ExtractableEntity::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true]);
    FileShare::create(['file_id' => $file->id, 'file_type' => 'document', 'shared_by_user_id' => $owner->id, 'shared_with_user_id' => $viewer->id, 'permission' => 'view', 'shared_at' => now()]);
    $this->browse(function (Browser $browser) use ($viewer, $file): void {
        $browser->resize(1440, 1000)->loginAs($viewer)->visit('/library?view=shared')->waitFor('@library-file-'.$file->id)
            ->assertDisabled('input[aria-label="Select all editable files on this page"]')
            ->assertDisabled('input[aria-label="Select Shared policy"]')->click('@library-file-'.$file->id)
            ->waitForText('A policy shared for reading.')->assertSee('Shared file · read only')
            ->assertDontSee('Edit details')
            ->assertScript('Array.from(document.querySelectorAll("aside[aria-label=\"Selected document\"] button")).some(el => el.textContent.trim() === "Approve")', false);
    });
});

it('keeps navigation discoverable and restores focus after closing the mobile drawer', function (): void {
    $user = $this->createUser();
    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(1440, 1000)->loginAs($user)->visit('/collections')->waitForText('My collections')
            ->assertScript('document.querySelectorAll("aside [aria-current=page]").length', 1)
            ->assertPresent('a[href$="/collections/shared"]')
            ->click('aside a[href$="/tags"]')->waitForLocation('/tags')->waitForText('Organization')
            ->assertPresent('nav[aria-label="Workspace navigation"] a[href$="/vendors"]');
        $browser->script("document.querySelector('a[href=\"#main-content\"]').focus()");
        $browser->keys('a[href="#main-content"]', WebDriverKeys::ENTER)
            ->assertScript('document.activeElement.id', 'main-content');
        foreach ([320, 390, 768] as $width) {
            $browser->resize($width, 844)->click('button[aria-label="Open navigation"]')
                ->waitFor('[role="dialog"]')->assertSee('Shared with me')
                ->assertScript('!!document.activeElement.closest("[role=dialog]")', true)
                ->keys('button[aria-label="Close navigation"]', WebDriverKeys::ESCAPE)
                ->waitUntilMissing('[role="dialog"]')
                ->waitUntil('document.activeElement.getAttribute("aria-label") === "Open navigation"')
                ->assertScript('document.activeElement.getAttribute("aria-label")', 'Open navigation')
                ->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
        }
    });
});

it('keeps shared controls readable and exposes keyboard focus in both themes', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->visit('/login')->waitFor('#email');
        $browser->script("localStorage.setItem('theme', 'light'); document.documentElement.classList.remove('dark'); document.querySelector('#email').focus();");
        $browser->assertScript('getComputedStyle(document.documentElement).colorScheme', 'light')
            ->assertScript("getComputedStyle(document.querySelector('form button')).textTransform", 'none')
            ->assertScript("getComputedStyle(document.querySelector('#email')).outlineStyle", 'solid')
            ->assertScript("parseFloat(getComputedStyle(document.querySelector('form button')).fontSize) >= 14", true);
        $browser->script("document.documentElement.classList.add('dark')");
        $browser->assertScript('getComputedStyle(document.documentElement).colorScheme', 'dark')
            ->assertScript("getComputedStyle(document.querySelector('#email')).outlineStyle", 'solid');
        $browser->script("document.documentElement.classList.remove('dark')");
    });
});

it('presents financial entity lists as compact responsive links with statement retrieval controls', function (): void {
    $user = $this->createUser();
    Invoice::factory()->for($user)->create([
        'invoice_number' => 'INV-COMPARE-42', 'from_name' => 'Northstar Works', 'payment_status' => 'unpaid',
        'total_amount' => 1250, 'amount_paid' => 250, 'amount_due' => 1000, 'invoice_date' => '2026-09-01', 'due_date' => '2026-10-30',
    ]);
    Contract::factory()->for($user)->create([
        'contract_title' => 'Workshop agreement', 'contract_number' => 'CT-042', 'status' => 'active',
        'effective_date' => '2026-01-01', 'expiry_date' => null, 'contract_value' => 5000,
    ]);
    Voucher::factory()->for($user)->create([
        'code' => 'GIFT-42', 'voucher_type' => 'gift_card', 'current_value' => 0, 'expiry_date' => null, 'is_redeemed' => false,
    ]);
    BankStatement::factory()->for($user)->create([
        'bank_name' => 'North Bank', 'account_holder_name' => 'Alex Morgan', 'account_number' => '123456789012',
        'statement_period_start' => '2026-09-01', 'statement_period_end' => '2026-09-30', 'closing_balance' => 4500,
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(1440, 1000)->loginAs($user)->visit('/invoices')->waitForText('INV-COMPARE-42')
            ->assertPresent('a[href*="/invoices/"]')->assertSee('Amount due')->assertSee('Unpaid');
        $browser->script("document.querySelector('a[href*=\"/invoices/\"]').focus()");
        $browser->assertScript('document.activeElement.tagName', 'A')
            ->visit('/contracts')->waitForText('Workshop agreement')->assertPresent('a[href*="/contracts/"]')
            ->assertSee('No expiry date')->assertSee('Active')
            ->visit('/vouchers')->waitForText('GIFT-42')->assertPresent('a[href*="/vouchers/"]')
            ->assertSee('Remaining value')->assertSee('Mark redeemed')
            ->visit('/bank-statements')->waitForText('North Bank')->assertPresent('a[href*="/bank-statements/"]')
            ->assertSee('2026-09-01 – 2026-09-30')->assertSee('Closing balance');

        $browser->type('#statement-search', 'Alex Morgan')
            ->waitUntil('new URL(location.href).searchParams.get("search") === "Alex Morgan"');
        foreach ([
            ['/invoices', 'INV-COMPARE-42'],
            ['/contracts', 'Workshop agreement'],
            ['/vouchers', 'GIFT-42'],
            ['/bank-statements', 'North Bank'],
        ] as [$path, $content]) {
            foreach ([320, 390, 768] as $width) {
                $browser->visit($path)->waitForText($content)->resize($width, 844)
                    ->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
                if ($path === '/bank-statements' && $width === 390) {
                    $browser->screenshot('redesign-financial-entities-390');
                }
            }
        }
        $browser->click('button[aria-label$="Switch to dark mode"]')
            ->waitUntil('document.documentElement.classList.contains("dark")')
            ->waitUntil("getComputedStyle(Array.from(document.querySelectorAll('a')).find(link => /\\/bank-statements\\/\\d+$/.test(link.pathname))).backgroundColor === 'rgb(39, 39, 42)'")
            ->screenshot('redesign-financial-entities-dark');
        $browser->click('button[aria-label$="Switch to light mode"]')->waitUntil('!document.documentElement.classList.contains("dark")');
        $browser->screenshot('redesign-financial-entities-mobile');
    });
});

it('puts financial summaries first and keeps recorded zero values visible in detail pages', function (): void {
    $user = $this->createUser();
    $invoiceFile = File::factory()->for($user)->create(['file_type' => 'invoice']);
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'file_id' => $invoiceFile->id,
        'invoice_number' => 'ZERO-INV', 'total_amount' => 0, 'amount_paid' => 0, 'amount_due' => 0,
        'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'shipping_amount' => 0,
        'currency' => 'USD', 'due_date' => null,
    ]);
    $contractFile = File::factory()->for($user)->create(['file_type' => 'contract']);
    $contract = Contract::factory()->create(['user_id' => $user->id, 'file_id' => $contractFile->id,
        'contract_title' => 'Zero value agreement', 'contract_value' => 0, 'currency' => 'USD', 'expiry_date' => null,
    ]);
    $voucherFile = File::factory()->for($user)->create(['file_type' => 'voucher']);
    $voucher = Voucher::factory()->create(['user_id' => $user->id, 'file_id' => $voucherFile->id,
        'code' => 'ZERO-GIFT', 'original_value' => 0, 'current_value' => 0, 'currency' => 'USD',
        'expiry_date' => null, 'is_redeemed' => false,
    ]);
    $statementFile = File::factory()->for($user)->create(['file_type' => 'bank_statement']);
    $statement = BankStatement::factory()->create(['user_id' => $user->id, 'file_id' => $statementFile->id,
        'bank_name' => 'Summary Bank', 'opening_balance' => 0, 'total_credits' => 0, 'total_debits' => 0,
        'closing_balance' => 0, 'currency' => 'USD']);
    BankTransaction::factory()->create(['user_id' => $user->id, 'bank_statement_id' => $statement->id,
        'transaction_date' => '2026-10-01', 'amount' => 0, 'currency' => 'USD', 'description' => 'Zero amount']);

    $this->browse(function (Browser $browser) use ($user, $invoice, $contract, $voucher, $statement): void {
        $browser->resize(390, 844)->loginAs($user)
            ->visit('/invoices/'.$invoice->id)->waitFor('section[aria-label="Invoice summary"]')
            ->assertSee('Amount due')->assertSee('No due date')
            ->assertScript("document.querySelector('section[aria-label=\"Invoice summary\"]').textContent.includes('0.00')", true)
            ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
            ->screenshot('task016-invoice-summary-mobile')
            ->visit('/contracts/'.$contract->id)->waitFor('section[aria-label="Contract summary"]')
            ->assertSee('No expiry date')
            ->assertScript("document.querySelector('section[aria-label=\"Contract summary\"]').textContent.includes('0.00')", true)
            ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
            ->screenshot('task016-contract-summary-mobile')
            ->visit('/vouchers/'.$voucher->id)->waitForText('Current Value')
            ->assertScript("document.querySelector('body').innerText.includes('Current Value')", true)
            ->assertScript("document.body.innerText.includes('0.00')", true)
            ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
            ->visit('/bank-statements/'.$statement->id)->waitForText('Balance Summary')
            ->assertSee('Transactions')->assertSee('Zero amount')
            ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
            ->screenshot('task016-bank-summary-mobile');
    });
});

it('renders populated workspaces with recognizable document and folder identities', function (): void {
    $user = $this->createUser(['name' => 'Alex Morgan']);
    $folder = Collection::factory()->for($user)->create(['name' => 'Home & property', 'description' => 'Agreements, maintenance and household records']);
    Collection::factory()->for($user)->create(['name' => 'Work expenses']);
    Collection::factory()->for($user)->create(['name' => 'Insurance', 'parent_id' => $folder->id]);
    $merchant = Merchant::create(['user_id' => $user->id, 'name' => 'Northstar Supply']);
    $receiptFile = File::factory()->for($user)->create(['fileName' => 'Northstar — September supplies.pdf', 'file_type' => 'receipt', 'status' => 'completed']);
    $receipt = Receipt::factory()->for($user)->create(['file_id' => $receiptFile->id, 'merchant_id' => $merchant->id, 'receipt_date' => '2026-09-18', 'total_amount' => 1240, 'tax_amount' => 248, 'currency' => 'NOK']);
    ExtractableEntity::create(['user_id' => $user->id, 'file_id' => $receiptFile->id, 'entity_type' => 'receipt', 'entity_id' => $receipt->id, 'is_primary' => true, 'confidence_score' => 0.96, 'extracted_at' => now()]);
    $file = File::factory()->for($user)->create(['fileName' => 'Property insurance 2026.pdf', 'file_type' => 'document', 'status' => 'completed']);
    $document = Document::factory()->for($user)->create(['file_id' => $file->id, 'title' => 'Property insurance', 'summary' => 'Policy and coverage for the coming year.']);
    ExtractableEntity::create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true, 'extracted_at' => now()]);
    $folder->files()->attach([$file->id, $receiptFile->id]);
    File::factory()->for($user)->create(['fileName' => 'October statement.pdf', 'status' => 'failed']);

    $this->browse(function (Browser $browser) use ($user, $folder): void {
        $browser->resize(1440, 1000)->loginAs($user);
        foreach ([
            ['dashboard', 'Recent uploads', 'home'],
            ['library', 'Property insurance', 'library'],
            ['collections', 'Home & property', 'collections'],
            ['collections/'.$folder->id, 'Property insurance', 'folder'],
            ['analytics', 'Receipt Spending', 'reports'],
            ['preferences', 'Application preferences', 'settings'],
        ] as [$path, $text, $name]) {
            $browser->visit('/'.$path)->waitForText($text)->assertPresent('main')
                ->screenshot('redesign-'.$name.'-desktop');
            $browser->resize(390, 844)->screenshot('redesign-'.$name.'-mobile');
            $browser->resize(1440, 1000);
        }
    });
});
