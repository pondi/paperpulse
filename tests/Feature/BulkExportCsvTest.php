<?php

declare(strict_types=1);

use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

it('requires authentication for bulk CSV export', function () {
    $this->post(route('bulk.receipts.export.csv'), ['receipt_ids' => [1]])
        ->assertRedirect(route('login'));
});

it('exports selected receipts as CSV with chunking', function () {
    $user = User::factory()->create();
    $merchant = Merchant::create(['name' => 'Bulk Store', 'user_id' => $user->id]);

    $receipts = Receipt::factory()->count(3)->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'receipt_date' => '2025-06-15',
        'total_amount' => 25.00,
        'tax_amount' => 3.00,
        'currency' => 'USD',
        'receipt_category' => 'Groceries',
        'receipt_description' => 'bulk-test-item',
    ]);

    $response = $this->actingAs($user)
        ->post(route('bulk.receipts.export.csv'), [
            'receipt_ids' => $receipts->pluck('id')->toArray(),
        ])
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');

    $content = $response->streamedContent();
    expect($content)->toContain('Receipt Date');
    expect($content)->toContain('Bulk Store');
    expect($content)->toContain('bulk-test-item');
});

it('includes line items in bulk CSV export', function () {
    $user = User::factory()->create();

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'total_amount' => 50.00,
    ]);

    LineItem::create([
        'receipt_id' => $receipt->id,
        'text' => 'Widget Alpha',
        'qty' => 2,
        'price' => 25.00,
    ]);

    $response = $this->actingAs($user)
        ->post(route('bulk.receipts.export.csv'), [
            'receipt_ids' => [$receipt->id],
        ])
        ->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('Widget Alpha');
    expect($content)->toContain('Qty: 2');
});

it('rejects other users receipts in bulk CSV export', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $ownReceipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'receipt_description' => 'my-receipt',
    ]);

    $otherReceipt = Receipt::factory()->create([
        'user_id' => $other->id,
        'receipt_description' => 'secret-receipt',
    ]);

    $this->actingAs($user)
        ->postJson(route('bulk.receipts.export.csv'), [
            'receipt_ids' => [$ownReceipt->id, $otherReceipt->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('receipt_ids.1');
});

it('does not hardcode currency in CSV export', function () {
    $user = User::factory()->create();

    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'currency' => 'EUR',
    ]);

    $response = $this->actingAs($user)
        ->post(route('bulk.receipts.export.csv'), [
            'receipt_ids' => [$receipt->id],
        ])
        ->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('EUR');
    expect($content)->not->toContain('NOK');
});

it('keeps bulk export feedback recoverable and follows queued exports', function (): void {
    $process = new Process(['node', '--input-type=module', '--eval', <<<'JS'
import assert from 'node:assert/strict';
import fs from 'node:fs';
import * as vue from 'vue';
import { parse, compileScript } from '@vue/compiler-sfc';
const calls = [];
let failure;
let response = { status: 202 };
const props = { selectedCount: 2, selectedIds: [11, 12], categories: [] };
const { descriptor } = parse(fs.readFileSync('resources/js/Components/Domain/BulkOperations.vue', 'utf8'));
const compiled = compileScript(descriptor, { id: 'bulk-actions', genDefaultAs: 'Component' });
const bindings = { route: name => name };
let code = compiled.content.replace(/import\s+\{([\s\S]*?)\}\s+from\s+['"]([^'"]+)['"];?/g, (_, names, module) => {
    for (const specifier of names.split(',').filter(value => value.trim())) {
        const [name, alias = name] = specifier.trim().split(/\s+as\s+/);
        bindings[alias] = module === 'vue' ? vue[name] : name === 'router' ? { visit: path => calls.push(path), post: (...args) => calls.push(args) } : () => null;
    }
    return '';
}).replace(/import\s+(\w+)\s+from\s+['"]([^'"]+)['"];?/g, (_, name, module) => {
    bindings[name] = module === 'axios' ? { post: async (...args) => { calls.push(args); if (failure) throw failure; return response; } } : () => null;
    return '';
});
const component = new Function(...Object.keys(bindings), code + '; return Component;')(...Object.values(bindings));
const events = [];
const state = component.setup(props, { expose: () => {}, emit: event => events.push(event) });
await state.exportSelected('pdf');
assert.equal(calls[0][0], 'bulk.receipts.export.pdf');
assert.deepEqual(calls[0][1], { receipt_ids: [11, 12] });
assert.equal(calls[0][2].headers.Accept, 'application/json');
assert.equal(calls.at(-1), 'exports.index');
assert.equal(state.busy.value, false);
failure = { response: { data: new Blob([JSON.stringify({ errors: { receipt_ids: ['Selection is no longer available.'] } })]) } };
await state.exportSelected('csv');
assert.equal(state.error.value, 'Selection is no longer available.');
assert.deepEqual(props.selectedIds, [11, 12]);
assert.deepEqual(events, []);
assert.equal(state.busy.value, false);
failure = new Error('Network unavailable');
await state.exportSelected('csv');
assert.match(state.error.value, /try again/);
assert.equal(state.busy.value, false);
state.open('categorize');
assert.equal(state.error.value, '');
state.categoryId.value = 9;
state.submit();
const mutation = calls.at(-1);
assert.deepEqual(mutation[1], { receipt_ids: [11, 12], category_id: 9 });
mutation[2].onError({ category_id: 'Category is no longer available.' });
mutation[2].onFinish();
assert.equal(state.modal.value, 'categorize');
assert.equal(state.error.value, 'Category is no longer available.');
assert.deepEqual(events, []);
state.submit();
calls.at(-1)[2].onSuccess();
calls.at(-1)[2].onFinish();
assert.equal(state.modal.value, null);
assert.deepEqual(events, ['clear-selection']);
JS], base_path());
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});
