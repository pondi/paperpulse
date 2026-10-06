<?php

use App\Jobs\GenerateArchiveExport;
use App\Models\ArchiveExport;
use App\Models\Document;
use App\Models\File;
use App\Models\Receipt;
use App\Models\User;
use App\Services\DocumentArchiveService;
use App\Services\Files\StoragePathBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Smalot\PdfParser\Parser;

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
    Storage::fake('paperpulse');
    config(['exports.immediate_limit' => 2, 'exports.chunk_size' => 2, 'broadcasting.default' => 'log']);
});

it('streams filtered CSV in bounded chunks without fetching the archive before streaming', function (): void {
    $user = User::factory()->create();
    Receipt::factory()->count(401)->create(['user_id' => $user->id, 'receipt_description' => 'Owned CSV row']);
    Receipt::factory()->create(['receipt_description' => 'Foreign secret']);
    $reads = [];
    DB::listen(function ($query) use (&$reads): void {
        if (str_contains($query->sql, 'from "receipts"') && str_starts_with($query->sql, 'select *')) {
            $reads[] = $query->sql;
        }
    });
    $response = $this->actingAs($user)->get(route('export.receipts.csv'))->assertOk();
    expect($reads)->toBe([]);
    $content = $response->streamedContent();
    expect(substr_count($content, 'Owned CSV row'))->toBe(401);
    expect($content)->not->toContain('Foreign secret');
    expect($reads)->toHaveCount(3);
    foreach ($reads as $sql) {
        expect($sql)->toContain('limit 200');
    }
});

it('validates export dates and sorting', function (array $filters, string $field): void {
    $this->actingAs(User::factory()->create())->getJson(route('export.receipts.csv', $filters))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['from_date' => '2026-02-30'], 'from_date'],
    [['from_date' => '2026-03-01', 'to_date' => '2026-02-01'], 'to_date'],
    [['sort' => 'untrusted_column'], 'sort'],
    [['sort_direction' => 'sideways'], 'sort_direction'],
]);

it('queues and generates large owned PDF exports in bounded pages with private downloads', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    Receipt::factory()->count(7)->create(['user_id' => $user->id, 'currency' => 'NOK', 'receipt_description' => 'Owned PDF']);
    Receipt::factory()->create(['receipt_description' => 'Foreign PDF secret']);
    $response = $this->actingAs($user)->getJson(route('export.receipts.pdf'))->assertAccepted();
    $export = ArchiveExport::findOrFail($response->json('id'));
    Queue::assertPushed(GenerateArchiveExport::class, fn ($job) => $job->exportId === $export->id && $job->connection === 'database' && $job->queue === 'exports');
    if (! getenv('PAPERPULSE_OFFICE_RUNTIME')) {
        Process::fake(function ($pending) {
            $output = substr(collect($pending->command)->first(fn ($argument) => str_starts_with($argument, '-sOutputFile=')), 13);
            copy(dirname($output).'/part-0.pdf', $output);

            return Process::result();
        });
    }
    app()->call([new GenerateArchiveExport($export->id), 'handle']);
    expect($export->refresh()->status)->toBe('completed')->and($export->processed)->toBe(7);
    expect(Storage::disk('local')->allDirectories())->not->toContain(dirname($export->path).'/work');
    $text = (new Parser)->parseFile(Storage::disk('local')->path($export->path))->getText();
    expect($text)->not->toContain('Foreign PDF secret');
    if (getenv('PAPERPULSE_OFFICE_RUNTIME')) {
        expect(count((new Parser)->parseFile(Storage::disk('local')->path($export->path))->getPages()))->toBe(4);
    }
    $status = $this->getJson(route('exports.status', $export))->assertOk()->assertJsonPath('processed', 7);
    $url = $status->json('download_url');
    $this->get($url)->assertDownload('archive.pdf');
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    $this->getJson(route('exports.status', $export))->assertNotFound();
    $this->actingAs($user)->get(route('exports.download', $export))->assertForbidden();
    $export->update(['expires_at' => now()->subMinute()]);
    $this->get($url)->assertGone();
    $this->artisan('exports:cleanup')->assertSuccessful();
    expect($export->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($export->path);
});

it('generates queued ZIP exports through database workers and omits missing assets', function (): void {
    $user = User::factory()->create();
    $documents = collect(range(1, 3))->map(function ($index) use ($user) {
        $file = File::factory()->create(['user_id' => $user->id, 'fileExtension' => 'pdf', 'fileName' => 'document.pdf']);
        if ($index !== 2) {
            Storage::disk('paperpulse')->put(StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf'), '%PDF-'.$index);
        }

        $file->update(['s3_original_path' => StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf')]);

        return Document::factory()->create(['user_id' => $user->id, 'file_id' => $file->id]);
    });
    $response = $this->actingAs($user)->getJson(route('documents.download-bulk', ['ids' => $documents->pluck('file_id')->all()]))->assertAccepted();
    $export = ArchiveExport::findOrFail($response->json('id'));
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'exports', '--once' => true, '--no-interaction' => true])->assertSuccessful();
    expect($export->refresh()->status)->toBe('completed')->and($export->processed)->toBe(3);
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($export->path), ZipArchive::RDONLY))->toBeTrue();
    expect($zip->numFiles)->toBe(2);
    expect($zip->getFromName('document.pdf'))->toBe('%PDF-1');
    expect($zip->getFromName('document_1.pdf'))->toBe('%PDF-3');
    $zip->close();
});

it('enforces active export limits independently for each user', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    ArchiveExport::factory()->count(2)->create(['user_id' => $user->id]);
    Receipt::factory()->count(3)->create(['user_id' => $user->id]);
    $this->actingAs($user)->getJson(route('export.receipts.pdf'))->assertUnprocessable()->assertJsonValidationErrors('export');
    Queue::assertNotPushed(GenerateArchiveExport::class);
    $other = User::factory()->create();
    Receipt::factory()->count(3)->create(['user_id' => $other->id]);
    $this->actingAs($other)->getJson(route('export.receipts.pdf'))->assertAccepted();
});

it('reports export failures without exposing service details and cleans work files', function (): void {
    $export = ArchiveExport::factory()->create(['format' => 'zip', 'filters' => ['ids' => [1]]]);
    $this->mock(DocumentArchiveService::class)->shouldReceive('write')->once()->andThrow(new RuntimeException('Secret service details'));
    $job = new GenerateArchiveExport($export->id);
    expect(fn () => app()->call([$job, 'handle']))->toThrow(RuntimeException::class);
    expect($export->refresh()->status)->toBe('failed');
    expect($export->error)->not->toContain('Secret');
    expect(Storage::disk('local')->exists('private/exports/'.$export->user_id.'/'.$export->id.'/work'))->toBeFalse();
    $this->actingAs($export->user)->getJson(route('exports.status', $export))->assertOk()->assertJsonPath('status', 'failed')->assertJsonPath('download_url', null);
});

it('shows only the owner export records on the progress page and JSON endpoint', function (): void {
    $owned = ArchiveExport::factory()->create();
    ArchiveExport::factory()->create();
    $this->actingAs($owned->user)->get(route('exports.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Exports/Index')->has('exports', 1)->where('exports.0.id', $owned->id));
    $this->getJson(route('exports.index'))->assertOk()->assertJsonCount(1, 'exports');
});

it('lists owned pending and expired exports with distinct states and no expired download', function (): void {
    $user = User::factory()->create();
    $pending = ArchiveExport::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
    $expired = ArchiveExport::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'expires_at' => now()->subMinute()]);
    ArchiveExport::factory()->create(['status' => 'completed', 'expires_at' => now()->subMinute()]);

    $response = $this->actingAs($user)->getJson(route('exports.index'))->assertOk()->assertJsonCount(2, 'exports');
    $exports = collect($response->json('exports'))->keyBy('id');
    expect($exports[$pending->id]['status'])->toBe('pending')
        ->and($exports[$expired->id]['status'])->toBe('expired')
        ->and($exports[$expired->id]['download_url'])->toBeNull();
});
