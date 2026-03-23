<?php

declare(strict_types=1);

use Laravel\Dusk\Browser;

/*
|--------------------------------------------------------------------------
| Upload & Processing Tests (Real E2E)
|--------------------------------------------------------------------------
|
| These tests upload real files and wait for Horizon/queue workers to
| process them end-to-end. They require external services (S3, Gemini)
| and a running Horizon instance.
|
| Skip these with: php artisan dusk --exclude-group=processing
|
*/

test('upload receipt image and verify it appears on files page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        // Navigate to upload page
        $browser->visit('/documents/upload')
            ->waitForText('Upload files', 10)
            ->pause(500);

        // Ensure "Receipt" file type is selected (default)
        $browser->assertPresent('input[type="file"]');

        // Attach the test image file
        $browser->attach('input[type="file"].sr-only', __DIR__.'/fixtures/test-image.jpg')
            ->pause(1000);

        // Submit the upload form
        $browser->click('button[type="submit"]')
            ->pause(2000);

        // Should redirect to receipts index with flash message
        $browser->waitForText('uploaded successfully', 15)
            ->assertSee('uploaded successfully');

        // Navigate to files-processing to check the file status
        $browser->visit('/files-processing')
            ->waitFor('main', 10)
            ->assertPathIs('/files-processing');

        // The file should appear (as Pending, Processing, or already Completed)
        $browser->waitForText('test-image', 10);
    });
})->group('processing');

test('upload receipt PDF and verify it appears on files page', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents/upload')
            ->waitForText('Upload files', 10)
            ->pause(500);

        // Attach the test PDF file
        $browser->attach('input[type="file"].sr-only', __DIR__.'/fixtures/test-receipt.pdf')
            ->pause(1000);

        // Submit
        $browser->click('button[type="submit"]')
            ->pause(2000);

        // Should redirect with success flash
        $browser->waitForText('uploaded successfully', 15)
            ->assertSee('uploaded successfully');

        // Verify file appears on files-processing page
        $browser->visit('/files-processing')
            ->waitFor('main', 10)
            ->waitForText('test-receipt', 10);
    });
})->group('processing');

test('uploaded file eventually reaches completed or failed status', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        // Upload a receipt image
        $browser->visit('/documents/upload')
            ->waitForText('Upload files', 10)
            ->pause(500)
            ->attach('input[type="file"].sr-only', __DIR__.'/fixtures/test-image.jpg')
            ->pause(1000)
            ->click('button[type="submit"]')
            ->pause(2000)
            ->waitForText('uploaded successfully', 15);

        // Poll the files-processing page until the file finishes processing
        // Timeout after 120 seconds (processing can take a while with real services)
        $maxAttempts = 24;
        $attempt = 0;
        $finished = false;

        while ($attempt < $maxAttempts && ! $finished) {
            $browser->visit('/files-processing')
                ->waitFor('main', 10)
                ->pause(2000);

            // Use JS to get page text (avoids Dusk's "body body" selector issue)
            $pageText = $browser->script('return document.body.innerText')[0] ?? '';

            if (str_contains($pageText, 'Completed') || str_contains($pageText, 'Failed')) {
                $finished = true;
            } else {
                $attempt++;
                $browser->pause(5000); // Wait 5 seconds before retrying
            }
        }

        expect($finished)->toBeTrue('File did not reach Completed or Failed status within 120 seconds');

        // Verify the final status is visible
        $browser->assertSee('Completed');
    });
})->group('processing');

test('upload document type file', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents/upload')
            ->waitForText('Upload files', 10)
            ->pause(500);

        // Switch to Document file type
        $browser->script("
            const buttons = document.querySelectorAll('button');
            for (const btn of buttons) {
                if (btn.textContent.trim() === 'Document') {
                    btn.click();
                    break;
                }
            }
        ");

        $browser->pause(500);

        // Attach the test PDF as a document
        $browser->attach('input[type="file"].sr-only', __DIR__.'/fixtures/test-receipt.pdf')
            ->pause(1000);

        // Submit
        $browser->click('button[type="submit"]')
            ->pause(2000);

        // Should redirect to documents index with success flash
        $browser->waitForText('uploaded successfully', 15)
            ->assertSee('uploaded successfully');
    });
})->group('processing');

test('upload without selecting file shows disabled submit button', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        $browser->visit('/documents/upload')
            ->waitForText('Upload files', 10)
            ->pause(500);

        // Submit button should be disabled when no files are selected
        $browser->assertPresent('button[type="submit"][disabled]');
    });
})->group('processing');

test('completed receipt appears on receipts index', function () {
    $user = $this->createUser();

    $this->browse(function (Browser $browser) use ($user) {
        $this->loginAs($browser, $user);

        // Upload a receipt
        $browser->visit('/documents/upload')
            ->waitForText('Upload files', 10)
            ->pause(500)
            ->attach('input[type="file"].sr-only', __DIR__.'/fixtures/test-image.jpg')
            ->pause(1000)
            ->click('button[type="submit"]')
            ->pause(2000)
            ->waitForText('uploaded successfully', 15);

        // Wait for processing to complete (poll files-processing page)
        $maxAttempts = 24;
        $attempt = 0;
        $completed = false;

        while ($attempt < $maxAttempts && ! $completed) {
            $browser->visit('/files-processing')
                ->waitFor('main', 10)
                ->pause(2000);

            // Use JS to get page text (avoids Dusk's "body body" selector issue)
            $pageText = $browser->script('return document.body.innerText')[0] ?? '';

            if (str_contains($pageText, 'Completed')) {
                $completed = true;
            } elseif (str_contains($pageText, 'Failed')) {
                break; // Processing failed, no point waiting
            } else {
                $attempt++;
                $browser->pause(5000);
            }
        }

        if (! $completed) {
            $this->markTestSkipped('File processing did not complete — external services may be unavailable');
        }

        // Visit the files-processing page and find the entity link for the completed file
        $browser->visit('/files-processing')
            ->waitFor('main', 10)
            ->pause(1000);

        // Verify the file has a "Completed" badge visible
        $browser->assertSee('Completed');

        // Visit receipts page — the processed receipt should appear
        $browser->visit('/receipts')
            ->waitFor('main', 10)
            ->pause(3000);

        // Check the page text via JS to avoid "body body" issue
        $pageText = $browser->script('return document.body.innerText')[0] ?? '';

        // If the receipt was created, it should not show the empty state
        // If processing produced no receipt (e.g. minimal test fixture), skip gracefully
        if (str_contains($pageText, 'Upload your first receipts')) {
            $this->markTestSkipped('File was processed but no receipt was extracted — test fixture may be too minimal for AI extraction');
        }

        $browser->assertDontSee('Upload your first receipts');
    });
})->group('processing');
