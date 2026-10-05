<?php

use App\Exceptions\DuplicateFileException;
use App\Models\Collection;
use App\Models\File;
use App\Models\Tag;
use App\Models\User;
use App\Services\FileProcessingService;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;

it('reports each repeated filename and preserves accepted uploads after later failures', function (): void {
    $user = User::factory()->create();
    $existing = File::factory()->make(['user_id' => $user->id, 'fileName' => 'Existing receipt']);
    $service = $this->mock(FileProcessingService::class);
    $service->shouldReceive('processUpload')->once()->ordered()
        ->withArgs(fn (UploadedFile $file, string $type, int $ownerId, array $metadata): bool => $type === 'receipt' && $ownerId === $user->id && $metadata['note'] === 'Batch note')
        ->andReturn(['success' => true, 'fileId' => 1]);
    $service->shouldReceive('processUpload')->once()->ordered()->andThrow(new DuplicateFileException($existing, 'hash'));
    $service->shouldReceive('processUpload')->once()->ordered()->andThrow(new RuntimeException('Private storage details'));
    $service->shouldReceive('processUpload')->once()->ordered()->andReturn(['success' => true, 'fileId' => 2]);

    $response = $this->actingAs($user)->from(route('documents.upload'))->post(route('documents.store'), [
        'file_type' => 'receipt',
        'note' => 'Batch note',
        'files' => [
            UploadedFile::fake()->image('same.png'),
            UploadedFile::fake()->image('same.png'),
            UploadedFile::fake()->image('same.png'),
            UploadedFile::fake()->createWithContent('same.png', 'invalid image'),
            UploadedFile::fake()->image('last.png'),
        ],
    ]);

    $response->assertRedirect(route('documents.upload'))->assertSessionHasNoErrors();
    $outcomes = session('upload_results');
    expect(array_column($outcomes, 'index'))->toBe([0, 1, 2, 3, 4])
        ->and(array_column($outcomes, 'status'))->toBe(['accepted', 'duplicate', 'failed', 'failed', 'accepted'])
        ->and(array_column($outcomes, 'filename'))->toBe(['same.png', 'same.png', 'same.png', 'same.png', 'last.png'])
        ->and($outcomes[1]['message'])->toContain('Existing receipt')
        ->and($outcomes[2]['message'])->not->toContain('Private storage details');

    $this->get(route('documents.upload'))->assertInertia(fn (Assert $page) => $page
        ->component('Documents/Upload')->where('flash.upload_results', $outcomes));
});

it('returns per-file failures when every file is invalid without processing any', function (): void {
    $service = $this->mock(FileProcessingService::class);
    $service->shouldNotReceive('processUpload');
    $this->actingAs(User::factory()->create())->from(route('documents.upload'))->post(route('documents.store'), [
        'file_type' => 'document',
        'files' => [UploadedFile::fake()->createWithContent('bad.png', 'not an image')],
    ])->assertRedirect(route('documents.upload'))->assertSessionHasNoErrors();

    expect(session('upload_results.0.status'))->toBe('failed');
});

it('rejects malformed upload envelopes before processing files', function (): void {
    $service = $this->mock(FileProcessingService::class);
    $service->shouldNotReceive('processUpload');
    $this->actingAs(User::factory()->create())->post(route('documents.store'), [
        'file_type' => 'foreign', 'files' => 'not files',
    ])->assertSessionHasErrors(['files', 'file_type']);
});

it('keeps tag and collection ownership validation before any upload is accepted', function (): void {
    $other = User::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $other->id]);
    $collection = Collection::factory()->create(['user_id' => $other->id]);
    $service = $this->mock(FileProcessingService::class);
    $service->shouldNotReceive('processUpload');

    $this->actingAs(User::factory()->create())->post(route('documents.store'), [
        'file_type' => 'receipt', 'files' => [UploadedFile::fake()->image('receipt.png')],
        'tag_ids' => [$tag->id], 'collection_ids' => [$collection->id],
    ])->assertSessionHasErrors(['tag_ids.0', 'collection_ids.0']);
});

it('retains only failed inputs and their metadata for retry in the upload page', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
import { parse, compileScript } from '@vue/compiler-sfc';
import { ref, watch } from 'vue';

const source = readFileSync('resources/js/Pages/Documents/Upload.vue', 'utf8');
const { descriptor } = parse(source);
const compiled = compileScript(descriptor, { id: 'upload-test', genDefaultAs: 'UploadPage' });
const code = stripTypeScriptTypes(compiled.content.replace(/^import .+$/gm, ''), { mode: 'strip' });
let callbacks;
let payload;
const revoked = [];
const bindings = {
    _defineComponent: component => component,
    ref, watch,
    useForm: data => ({ post: (url, options) => { payload = data; callbacks = options; } }),
    route: () => '/documents/store',
    URL: { revokeObjectURL: url => revoked.push(url) },
    AuthenticatedLayout: {}, CollectionSelector: {}, TagSelector: {}, Head: {},
    XMarkIcon: {}, PhotoIcon: {}, DocumentIcon: {}, ReceiptRefundIcon: {},
};
const component = new Function(...Object.keys(bindings), code + '; return UploadPage;')(...Object.values(bindings));
const page = component.setup({}, { expose() {} });
const entries = [0, 1, 2].map(index => ({ file: { name: 'same.png', index }, preview: 'blob:' + index }));
page.selectedFiles.value = entries;
page.note.value = 'Keep this note';
page.collectionIds.value = [7];
page.tagIds.value = [8];
page.submit();
assert.deepEqual(payload.files, entries.map(entry => entry.file));
callbacks.onSuccess({ props: { flash: { upload_results: [
    { index: 0, filename: 'same.png', status: 'accepted', message: 'Accepted' },
    { index: 1, filename: 'same.png', status: 'duplicate', message: 'Duplicate' },
    { index: 2, filename: 'same.png', status: 'failed', message: 'Failed' },
] } } });
callbacks.onFinish();
assert.deepEqual(page.uploadResults.value.map(result => result.status), ['duplicate', 'failed']);
assert.deepEqual(page.selectedFiles.value.map(entry => entry.file.index), [2]);
assert.deepEqual(revoked, ['blob:0', 'blob:1']);
assert.equal(page.note.value, 'Keep this note');
assert.deepEqual(page.collectionIds.value, [7]);
assert.deepEqual(page.tagIds.value, [8]);
assert.equal(page.isUploading.value, false);
page.submit();
assert.deepEqual(payload.files.map(file => file.index), [2]);
callbacks.onSuccess({ props: { flash: { upload_results: [
    { index: 0, filename: 'same.png', status: 'accepted', message: 'Accepted' },
] } } });
callbacks.onFinish();
assert.deepEqual(page.uploadResults.value, []);
assert.equal(page.selectedFiles.value.length, 0);
assert.equal(page.note.value, '');
assert.deepEqual(page.collectionIds.value, []);
assert.deepEqual(page.tagIds.value, []);
assert.deepEqual(revoked, ['blob:0', 'blob:1', 'blob:2']);
JS;

    $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});
