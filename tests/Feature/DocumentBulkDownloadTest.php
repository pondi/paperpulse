<?php

use App\Models\Contract;
use App\Models\Document;
use App\Models\File;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Files\StoragePathBuilder;
use App\Services\StorageService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

it('downloads owned documents and skips unavailable assets', function (): void {
    Storage::fake('paperpulse');
    $user = User::factory()->create();
    $documents = collect(['first.pdf', 'missing.pdf', 'last.pdf'])->map(function (string $name) use ($user): Document {
        $file = File::factory()->create([
            'user_id' => $user->id,
            'file_type' => 'document',
            'fileExtension' => 'pdf',
            'fileName' => $name,
        ]);

        if ($name !== 'missing.pdf') {
            Storage::disk('paperpulse')->put(
                StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf'),
                '%PDF-'.$name
            );
        }

        $file->update(['s3_original_path' => StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf')]);

        return Document::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'title' => 'A different document title']);
    });

    $response = $this->actingAs($user)
        ->get(route('documents.download-bulk', ['ids' => $documents->pluck('file_id')->all()]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip');

    $zipPath = tempnam(sys_get_temp_dir(), 'document-bulk-test');
    $zip = new ZipArchive;

    try {
        file_put_contents($zipPath, $response->streamedContent());
        expect($zip->open($zipPath))->toBeTrue();
        expect($zip->numFiles)->toBe(2);
        expect($zip->getFromName('first.pdf'))->toBe('%PDF-first.pdf');
        expect($zip->getFromName('last.pdf'))->toBe('%PDF-last.pdf');
        expect($zip->locateName('missing.pdf'))->toBeFalse();
        $zip->close();
    } finally {
        unlink($zipPath);
    }
});

it('preserves safe uploaded filenames and resolves ZIP collisions', function (): void {
    Storage::fake('paperpulse');
    $user = User::factory()->create();
    $names = ['Årsrapport 2026.PDF', 'report.pdf', 'report_1.pdf', 'report.pdf', '../report.pdf', 'folder\\report.pdf', "report\n.pdf", 'extensionless'];
    $documents = collect($names)->map(function (string $name, int $index) use ($user): Document {
        $file = File::factory()->create([
            'user_id' => $user->id,
            'file_type' => 'document',
            'fileExtension' => 'pdf',
            'fileName' => $name,
        ]);
        Storage::disk('paperpulse')->put(
            StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf'),
            '%PDF-'.$index
        );

        $file->update(['s3_original_path' => StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf')]);

        return Document::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'title' => 'A different document title']);
    });

    $response = $this->actingAs($user)
        ->get(route('documents.download-bulk', ['ids' => $documents->pluck('file_id')->all()]))
        ->assertOk();
    $zipPath = tempnam(sys_get_temp_dir(), 'document-bulk-test');
    $zip = new ZipArchive;

    try {
        file_put_contents($zipPath, $response->streamedContent());
        expect($zip->open($zipPath))->toBeTrue()
            ->and($zip->numFiles)->toBe(count($names));

        foreach (['Årsrapport 2026.PDF', 'report.pdf', 'report_1.pdf', 'report_2.pdf', '.._report.pdf', 'folder_report.pdf', 'report_.pdf', 'extensionless.pdf'] as $index => $name) {
            expect($zip->getFromName($name))->toBe('%PDF-'.$index);
        }

        $zip->close();
    } finally {
        unlink($zipPath);
    }
});

it('downloads the uploaded filename instead of the entity title', function (string $model, string $route, string $name, string $expected): void {
    $entity = $model::factory()->create();
    $entity->file->update(['fileName' => $name, 'fileExtension' => 'pdf']);
    $this->mock(StorageService::class)
        ->shouldReceive('getFileByUserAndGuid')
        ->once()
        ->andReturn('%PDF-original');

    $this->actingAs($entity->user)
        ->get(route($route, $entity))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$expected.'"');
})->with([
    'document' => [Document::class, 'documents.download'],
    'invoice' => [Invoice::class, 'invoices.download'],
    'contract' => [Contract::class, 'contracts.download'],
    'voucher' => [Voucher::class, 'vouchers.download'],
])->with([
    'safe filename' => ['uploaded-report.pdf', 'uploaded-report.pdf'],
    'unsafe header characters' => ["report\"\r\n.pdf", 'report___.pdf'],
]);

it('rejects invalid document selections before reading storage', function (string $selection): void {
    $owned = Document::factory()->create();
    $user = $owned->user;
    $foreign = Document::factory()->create();
    $ids = match ($selection) {
        'foreign' => [$foreign->file_id],
        'mixed' => [$owned->id, $foreign->file_id],
        'nonexistent' => [$foreign->file_id + 1],
        'empty' => [],
    };
    $this->mock(StorageService::class)->shouldNotReceive('getFileByUserAndGuid');

    $this->actingAs($user)
        ->getJson(route('documents.download-bulk', ['ids' => $ids]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($selection === 'empty' ? 'ids' : 'ids.'.($selection === 'mixed' ? '1' : '0'));
})->with(['foreign', 'mixed', 'nonexistent', 'empty']);

it('returns a valid empty ZIP when every selected asset is missing', function (): void {
    Storage::fake('paperpulse');
    $document = Document::factory()->create();
    $response = $this->actingAs($document->user)->get(route('documents.download-bulk', ['ids' => [$document->file_id]]))->assertOk();
    expect($response->streamedContent())->toBe("PK\x05\x06".str_repeat("\0", 18));
});

it('downloads and deletes only selected canonical files with colliding entity IDs', function (): void {
    Storage::fake('paperpulse');
    $owner = User::factory()->create();
    $files = File::factory()->count(2)->create(['user_id' => $owner->id, 'fileExtension' => 'pdf']);
    $document = Document::factory()->create(['id' => 700, 'user_id' => $owner->id, 'file_id' => $files[0]->id]);
    $invoice = Invoice::factory()->create(['id' => 700, 'user_id' => $owner->id, 'file_id' => $files[1]->id]);
    $files[1]->update(['fileName' => 'invoice.pdf', 's3_original_path' => 'documents/'.$owner->id.'/invoice/original.pdf']);
    Storage::disk('paperpulse')->put($files[1]->fresh()->s3_original_path, '%PDF-invoice');
    $this->actingAs($owner);
    $response = $this->get(route('documents.download-bulk', ['ids' => [$files[1]->id]]))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'mixed-files');
    try {
        file_put_contents($path, $response->streamedContent());
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue()->and($zip->numFiles)->toBe(1)
            ->and($zip->getFromName('invoice.pdf'))->toBe('%PDF-invoice');
        $zip->close();
    } finally {
        unlink($path);
    }
    $this->delete(route('documents.destroy-bulk'), ['ids' => [$files[1]->id]])->assertRedirect();
    expect($files[1]->fresh()->trashed())->toBeTrue()->and($invoice->fresh()->trashed())->toBeTrue()
        ->and($files[0]->fresh()->trashed())->toBeFalse()->and($document->fresh()->trashed())->toBeFalse();
});

it('rejects foreign files before bulk deletion', function (): void {
    $owned = File::factory()->create();
    $foreign = File::factory()->create();
    $this->actingAs($owned->user)->deleteJson(route('documents.destroy-bulk'), ['ids' => [$owned->id, $foreign->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('ids.1');
    expect($owned->fresh()->trashed())->toBeFalse()->and($foreign->fresh()->trashed())->toBeFalse();
});

it('keeps mixed selections and drawer deletion tied to file identity', function (): void {
    $process = new Process(['node', '--input-type=module', '--eval', <<<'JS'
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
import * as vue from 'vue';
import { parse, compileScript } from '@vue/compiler-sfc';
const calls = [];
function setup(file, props) {
    const { descriptor } = parse(fs.readFileSync('resources/js/' + file, 'utf8'));
    const compiled = compileScript(descriptor, { id: file, genDefaultAs: 'Component' });
    const bindings = { route: name => name };
    let code = compiled.content.replace(/import\s+\{([\s\S]*?)\}\s+from\s+['"]([^'"]+)['"];?/g, (_, names, module) => {
        for (const specifier of names.split(',').filter(value => value.trim())) {
            const [name, alias = name] = specifier.trim().split(/\s+as\s+/);
            bindings[alias] = module === 'vue' ? ['onMounted', 'onUnmounted'].includes(name) ? () => {} : vue[name]
                : name === 'router' ? { delete: (...args) => calls.push(args) } : name === 'useDateFormatter' ? () => ({}) : () => null;
        }
        return '';
    }).replace(/import\s+(\w+)\s+from\s+['"][^'"]+['"];?/g, (_, name) => { bindings[name] = () => null; return ''; });
    code = stripTypeScriptTypes(code, { mode: 'strip' });
    const component = new Function(...Object.keys(bindings), code + '; return Component;')(...Object.values(bindings));
    return { state: component.setup(props, { expose: () => {}, emit: () => {} }), template: descriptor.template.content };
}
const rows = [{ id: 7, file_id: 101 }, { id: 7, file_id: 102 }];
const { state, template } = setup('Pages/Documents/Index.vue', { documents: { data: rows }, categories: [], filters: {} });
assert.ok(template.includes('toggleDocument(document.file_id)'));
assert.ok(template.includes('selectedDocuments.includes(document.file_id)'));
state.toggleDocument(rows[0].file_id);
assert.deepEqual(state.selectedDocuments.value, [101]);
state.deleteSelected();
assert.deepEqual(calls.at(-1)[1].data.ids, [101]);
state.toggleAll();
assert.deepEqual(state.selectedDocuments.value, [101, 102]);
const drawer = setup('Components/Domain/DocumentDrawer.vue', { document: rows[1] });
drawer.state.deleteDocument();
assert.equal(calls.at(-1)[0], 'documents.destroy-bulk');
assert.deepEqual(calls.at(-1)[1].data.ids, [102]);
JS], base_path());
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});
