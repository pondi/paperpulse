<?php

use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\SavedSearch;
use App\Services\Files\StoragePathBuilder;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;

test('library filters and saved views work through their complete browser lifecycle', function (): void {
    $user = $this->createUser();
    $file = File::factory()->create(['user_id' => $user->id, 'fileName' => 'Business October.pdf', 'status' => 'completed', 'file_type' => 'document']);
    $other = File::factory()->create(['user_id' => $user->id, 'fileName' => 'Home insurance.pdf', 'status' => 'needs_review']);

    $this->browse(function (Browser $browser) use ($user, $file, $other): void {
        $browser->resize(1440, 1000);
        $this->loginAs($browser, $user);
        $browser->visit('/library')->waitFor('@library-query')->assertSee('Business October.pdf')->assertSee('Home insurance.pdf')
            ->screenshot('library-desktop')->type('@library-query', 'Business')
            ->waitUntilMissing('@library-file-'.$other->id)
            ->assertPresent('@library-file-'.$file->id)->click('button[aria-label="Grid view"]')
            ->waitFor('[aria-label="Grid view"][aria-pressed="true"]')->click('@save-view')->waitFor('@saved-view-name')
            ->type('@saved-view-name', 'Business documents')->click('@confirm-save-view')->waitForText('Save changes')
            ->assertQueryStringHas('query', 'Business')->assertQueryStringHas('display', 'grid')->assertSee('Business documents');
        $saved = SavedSearch::where('user_id', $user->id)->firstOrFail();
        expect($saved->filters['query'])->toBe('Business')->and($saved->filters['display'])->toBe('grid');
        $browser->refresh()->waitFor('@library-query')->assertInputValue('@library-query', 'Business')
            ->click('@library-file-'.$file->id)->waitForText('Document information')->click('@workspace-back')
            ->waitFor('@library-query')->assertInputValue('@library-query', 'Business')->assertQueryStringHas('saved_search', (string) $saved->id)
            ->click('aside a[href$="/saved-views"]')->waitForText('Your views')->screenshot('saved-views-desktop')
            ->click('button[aria-label="Rename Business documents"]')->waitFor('#rename-view')->type('#rename-view', 'Business archive')
            ->press('Save name')->waitForText('Business archive')->click('button[aria-label="Unpin Business archive"]')
            ->waitFor('button[aria-label="Pin Business archive"]')->click('button[aria-label="Pin Business archive"]')
            ->waitFor('button[aria-label="Unpin Business archive"]')
            ->click('button[aria-label="Delete Business archive"]')->waitForText('Remove “Business archive”?')->press('Remove view')
            ->waitForText('Make your library work for you');
        expect(SavedSearch::where('user_id', $user->id)->count())->toBe(0);
        expect($file->fresh())->not->toBeNull();
    });
});

test('document workspace previews the original beside extracted information and mobile navigation stays usable', function (): void {
    $user = $this->createUser();
    $file = File::factory()->create(['user_id' => $user->id, 'fileName' => 'Service agreement — Northstar Studio.pdf', 'file_type' => 'document', 'fileExtension' => 'pdf', 'status' => 'completed']);
    $path = StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf');
    Storage::disk('paperpulse')->put($path, conversionPdfFixture());
    $file->update(['s3_original_path' => $path]);
    $document = Document::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'title' => 'Northstar Studio service agreement',
        'summary' => 'A twelve-month service agreement covering design, delivery and ongoing support.']);
    ExtractableEntity::create(['user_id' => $user->id, 'file_id' => $file->id, 'entity_type' => 'document', 'entity_id' => $document->id, 'is_primary' => true, 'extracted_at' => now()]);

    try {
        $this->browse(function (Browser $browser) use ($user, $file): void {
            $browser->resize(1440, 1000);
            $this->loginAs($browser, $user);
            $browser->visit('/library')->waitFor('@library-file-'.$file->id)->click('@library-file-'.$file->id)
                ->waitForText('Document information')->assertSee('A twelve-month service agreement')->assertPresent('iframe[title]')
                ->assertPresent('a[target="_blank"][rel="noopener noreferrer"]')->screenshot('document-workspace-desktop');
            $browser->resize(390, 844)->screenshot('document-workspace-mobile');
            $browser->assertSee('Document information')->assertSee('A twelve-month service agreement');
            expect($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0])->toBeTrue();
            $browser->click('button[aria-label="Open navigation"]')->waitFor('button[aria-label="Close navigation"]')
                ->click('[role="dialog"] a[href$="/library"]')->waitFor('@library-query')->waitUntilMissing('[role="dialog"]')
                ->screenshot('library-mobile');
            expect($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0])->toBeTrue();
            $browser->click('@add-document')->waitForText('Upload files')->assertSee('Scan a document')->assertSee('Import from scanner');
            $browser->click('@add-document')->waitUntilMissing('[role="menu"]')->type('input[aria-label="Search your library"]', 'Northstar')
                ->waitFor('ul[aria-label="Search results"] button')->click('ul[aria-label="Search results"] button')
                ->waitForText('Open document workspace')->assertSee('Northstar Studio service agreement');
            expect($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0])->toBeTrue();
            $browser->click('[role="dialog"] a[href*="/files/'.$file->id.'?"]')->waitForText('Document information');
        });
    } finally {
        Storage::disk('paperpulse')->delete($path);
    }
});

test('full text saved searches restore filters and remain editable after navigation', function (): void {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->resize(1440, 1000);
        $this->loginAs($browser, $user);
        $browser->visit('/search?query=travel&type=receipt')->waitFor('input[placeholder^="Search receipts"]')
            ->assertChecked('input[type="radio"][value="receipt"]')
            ->click('@save-view')->waitFor('@saved-view-name')->type('@saved-view-name', 'Travel expenses')->click('@confirm-save-view')
            ->waitForText('Save changes')->assertQueryStringHas('query', 'travel')->assertSee('Travel expenses')
            ->refresh()->waitFor('input[placeholder^="Search receipts"]')->assertChecked('input[type="radio"][value="receipt"]')
            ->type('input[placeholder^="Search receipts"]', 'train')
            ->waitUsing(10, 100, fn (): bool => str_contains($browser->driver->getCurrentURL(), 'query=train'))
            ->click('@save-view')->waitFor('@saved-view-name')->click('@confirm-save-view')->waitUntilMissing('@saved-view-name');
        expect(SavedSearch::where('user_id', $user->id)->firstOrFail()->filters['query'])->toBe('train');
    });
});
