<?php

declare(strict_types=1);

use App\Jobs\Files\ProcessFileGemini;
use App\Models\Document;
use App\Models\File;
use App\Models\JobHistory;
use App\Models\Receipt;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->uploadUser = $this->createUser();
    config(['queue.default' => 'database', 'ai.file_processing_provider' => 'gemini']);
});

afterEach(function (): void {
    $this->browse(function (Browser $browser): void {
        $errors = array_filter($browser->driver->manage()->getLog('browser'),
            fn (array $entry): bool => $entry['level'] === 'SEVERE');
        expect(array_values($errors))->toBe([]);
    });
    $files = File::withoutGlobalScope('user')->withTrashed()->where('user_id', $this->uploadUser->id)->get();
    foreach ($files as $file) {
        foreach (['receipts', 'documents'] as $folder) {
            Storage::disk('paperpulse')->deleteDirectory($folder.'/'.$this->uploadUser->id.'/'.$file->guid);
        }
    }
});

function fakeBrowserProcessingProvider(string $entityType = 'receipt', bool $refuseExtraction = false): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => function (Request $request) use ($entityType, $refuseExtraction): PromiseInterface {
        if ($request->hasHeader('X-Goog-Upload-Command', 'start')) {
            return Http::response([], 200, ['X-Goog-Upload-URL' => 'https://generativelanguage.googleapis.com/upload/browser-fixture']);
        }
        if ($request->hasHeader('X-Goog-Upload-Command', 'upload, finalize')) {
            return Http::response(['file' => [
                'uri' => 'https://generativelanguage.googleapis.com/v1beta/files/browser-fixture',
                'name' => 'files/browser-fixture', 'state' => 'ACTIVE',
                'mimeType' => $request->header('Content-Type')[0],
            ]]);
        }
        if ($request->method() === 'DELETE') {
            return Http::response([], 200);
        }
        if (str_contains($request->url(), ':countTokens')) {
            return Http::response(['totalTokens' => 100]);
        }
        if (! str_contains($request->url(), ':generateContent')) {
            throw new RuntimeException('Unexpected provider request: '.$request->method().' '.$request->url());
        }
        $isClassification = isset($request['generationConfig']['responseJsonSchema']['properties']['confidence']);
        if (! $isClassification && $refuseExtraction) {
            return Http::response(['candidates' => [['finishReason' => 'SAFETY']]]);
        }
        $data = $isClassification
            ? ['document_type' => $entityType, 'confidence' => 0.99, 'reasoning' => 'Isolated browser fixture']
            : ($entityType === 'document'
                ? ['document_title' => 'Local E2E Document', 'document_type' => 'report',
                    'summary' => 'A document processed through the local Docker stack.', 'confidence_score' => 0.99]
                : ['merchant_name' => 'Local E2E Store', 'receipt_date' => '2026-09-15',
                    'total_amount' => 42.50, 'currency' => 'NOK', 'description' => 'Local browser purchase',
                    'category' => 'Groceries', 'confidence_score' => 0.99,
                    'items' => [['name' => 'Browser fixture item', 'quantity' => 1, 'unit_price' => 42.50, 'total_price' => 42.50]]]);

        return Http::response(['candidates' => [['finishReason' => 'STOP',
            'content' => ['parts' => [['text' => json_encode($data, JSON_THROW_ON_ERROR)]]]]]]);
    }]);
}

function uploadBrowserFixture(Browser $browser, string $filename = 'test-image.jpg', string $fileType = 'receipt'): void
{
    $browser->visit('/documents/upload')->waitForText('Upload Your Documents');
    if ($fileType === 'document') {
        $browser->click('button[class*="rounded-r-lg"]');
    }
    $browser->attach('input[type="file"].sr-only', __DIR__.'/fixtures/'.$filename)
        ->waitForText('Upload 1 file')
        ->click('button[type="submit"]')
        ->waitForText('Upload 0 files', 15)
        ->assertSeeIn('[data-upload-outcome="accepted"]', '1 file queued for processing')
        ->assertPresent('[data-upload-outcome="accepted"].bg-green-50')
        ->assertDontSee('Accepted for processing.');
}

function runBrowserProcessingQueue(): void
{
    test()->artisan('queue:work', [
        'connection' => 'database', '--queue' => implode(',', config('queue.worker_queues')),
        '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1,
        '--memory' => 512, '--max-time' => 30, '--no-interaction' => true,
    ])->assertSuccessful();
}

test('upload receipt image and verify it appears on files page', function (): void {
    fakeBrowserProcessingProvider();
    $this->browse(function (Browser $browser): void {
        $this->loginAs($browser, $this->uploadUser);
        uploadBrowserFixture($browser);
        $browser->screenshot('upload-accepted-alert');
        $browser->visit('/files-processing')->waitForText('test-image');
    });

    $file = File::query()->where('user_id', $this->uploadUser->id)->sole();
    expect($file->status)->toBe('pending');
    expect(Storage::disk('paperpulse')->get($file->s3_original_path))->toBe(file_get_contents(__DIR__.'/fixtures/test-image.jpg'));
    runBrowserProcessingQueue();
    expect($file->fresh()->status)->toBe('completed');
});

test('upload receipt PDF and verify it appears on files page', function (): void {
    fakeBrowserProcessingProvider();
    $this->browse(function (Browser $browser): void {
        $this->loginAs($browser, $this->uploadUser);
        uploadBrowserFixture($browser, 'test-receipt.pdf');
        $browser->visit('/files-processing')->waitForText('test-receipt');
    });

    runBrowserProcessingQueue();
    $file = File::query()->where('user_id', $this->uploadUser->id)->sole();
    expect($file->status)->toBe('completed');
    expect($file->has_image_preview)->toBeTrue();
    expect(Storage::disk('paperpulse')->exists($file->s3_image_path))->toBeTrue();
});

test('uploaded file reaches completed status through the database queue', function (): void {
    fakeBrowserProcessingProvider();
    $this->browse(function (Browser $browser): void {
        $this->loginAs($browser, $this->uploadUser);
        uploadBrowserFixture($browser);
        runBrowserProcessingQueue();
        $browser->visit('/files-processing')->waitForText('Completed')->assertSee('Completed');
    });

    $file = File::query()->where('user_id', $this->uploadUser->id)->sole();
    expect($file->status)->toBe('completed');
    expect(JobHistory::query()->where('file_id', $file->id)->whereNull('parent_uuid')->sole()->status)->toBe('completed');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), 'files/browser-fixture'));
});

test('upload document type file', function (): void {
    fakeBrowserProcessingProvider('document');
    $this->browse(function (Browser $browser): void {
        $this->loginAs($browser, $this->uploadUser);
        uploadBrowserFixture($browser, 'test-receipt.pdf', 'document');
        runBrowserProcessingQueue();
        $browser->visit('/documents')->waitForText('Local E2E Document')->assertSee('Local E2E Document');
    });

    $document = Document::query()->where('user_id', $this->uploadUser->id)->sole();
    $this->actingAs($this->uploadUser)->get(route('documents.download', $document))->assertOk();
    $this->actingAs($this->createUser())->get(route('documents.download', $document))->assertNotFound();
});

test('upload without selecting file shows disabled submit button', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->loginAs($browser, $this->uploadUser);
        $browser->visit('/documents/upload')->waitForText('Upload Your Documents')
            ->assertPresent('button[type="submit"][disabled]');
    });
    expect(File::query()->where('user_id', $this->uploadUser->id)->count())->toBe(0);
});

test('completed receipt appears on receipts index with a working private preview', function (): void {
    fakeBrowserProcessingProvider();
    $this->browse(function (Browser $browser): void {
        $this->loginAs($browser, $this->uploadUser);
        uploadBrowserFixture($browser);
        runBrowserProcessingQueue();
        $receipt = Receipt::query()->where('user_id', $this->uploadUser->id)->sole();
        expect((float) $receipt->total_amount)->toBe(42.50);
        expect($receipt->currency)->toBe('NOK');
        $browser->visit('/receipts')->waitForText('Local E2E Store')
            ->assertDontSee('Upload your first receipts')
            ->visit('/receipts/'.$receipt->id)->waitForText('Browser fixture item')
            ->waitUntil(<<<'JS'
                document.querySelector('img[src*="/image"]')?.naturalWidth > 0
            JS);
    });
});

test('duplicate browser uploads keep one file and report the existing filename', function (): void {
    fakeBrowserProcessingProvider();
    $this->browse(function (Browser $browser): void {
        $this->loginAs($browser, $this->uploadUser);
        uploadBrowserFixture($browser);
        $browser->visit('/documents/upload')->waitForText('Upload Your Documents')
            ->attach('input[type="file"].sr-only', __DIR__.'/fixtures/test-image.jpg')
            ->waitForText('Upload 1 file')->click('button[type="submit"]')
            ->waitForText('Already exists as "test-image.jpg"')
            ->assertPresent('[data-upload-outcome="duplicate"].bg-amber-50')
            ->assertSeeIn('[data-upload-outcome="duplicate"]', '1 file already uploaded')
            ->screenshot('upload-duplicate-alert')
            ->click('[data-upload-outcome="duplicate"] button')
            ->assertMissing('[data-upload-outcome="duplicate"]');
    });

    expect(File::query()->where('user_id', $this->uploadUser->id)->count())->toBe(1);
    runBrowserProcessingQueue();
    expect(Receipt::query()->where('user_id', $this->uploadUser->id)->count())->toBe(1);
});

test('provider refusal is shown as failed and can be retried from the browser', function (): void {
    fakeBrowserProcessingProvider(refuseExtraction: true);
    $this->browse(function (Browser $browser): void {
        $this->loginAs($browser, $this->uploadUser);
        uploadBrowserFixture($browser);
        $file = File::query()->where('user_id', $this->uploadUser->id)->sole();
        $job = new ProcessFileGemini(JobHistory::query()->where('file_id', $file->id)->whereNull('parent_uuid')->sole()->uuid);
        try {
            for ($attempt = 0; $attempt < $job->tries; $attempt++) {
                runBrowserProcessingQueue();
                $this->travel(max($job->backoff) + 1)->seconds();
            }
        } finally {
            $this->travelBack();
        }
        $browser->visit('/files-processing')->waitForText('RETRY PROCESSING')->assertSee('Failed');
    });

    expect(File::query()->where('user_id', $this->uploadUser->id)->sole()->status)->toBe('failed');
    expect(Receipt::query()->where('user_id', $this->uploadUser->id)->count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), 'files/browser-fixture'));

    fakeBrowserProcessingProvider();
    $this->browse(function (Browser $browser): void {
        $browser->click('main button.bg-zinc-900')->waitUntilMissingText('RETRY PROCESSING');
        runBrowserProcessingQueue();
        $browser->visit('/files-processing')->waitForText('Completed');
    });
    expect(File::query()->where('user_id', $this->uploadUser->id)->sole()->status)->toBe('completed');
    expect(Receipt::query()->where('user_id', $this->uploadUser->id)->count())->toBe(1);
});

test('office uploads convert to PDF and display their extracted document', function (): void {
    fakeBrowserProcessingProvider('document');
    $this->browse(function (Browser $browser): void {
        $this->loginAs($browser, $this->uploadUser);
        $browser->visit('/documents/upload')->waitForText('Upload Your Documents')
            ->click('button[class*="rounded-r-lg"]')
            ->attach('input[type="file"].sr-only', base_path('tests/fixtures/office/fixture.docx'))
            ->waitForText('Upload 1 file')->click('button[type="submit"]')
            ->waitForText('Upload 0 files', 15)
            ->assertSeeIn('[data-upload-outcome="accepted"]', '1 file queued for processing')
            ->assertDontSee('Accepted for processing.');
        runBrowserProcessingQueue();
        $file = File::query()->where('user_id', $this->uploadUser->id)->sole();
        expect($file->status)->toBe('completed');
        expect($file->has_image_preview)->toBeTrue();
        expect(Storage::disk('paperpulse')->get($file->s3_archive_path))->toStartWith('%PDF-');
        $browser->visit('/documents')->waitForText('Local E2E Document');
    });
});
