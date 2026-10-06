<?php

use App\Models\Category;
use App\Models\Document;
use App\Models\File;
use App\Models\FileShare;
use App\Models\User;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Process\Process;
use Tighten\Ziggy\Ziggy;

beforeEach(function (): void {
    $this->withoutVite();
});

it('renders the audited live UI contracts', function (string $scenario): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
import * as vue from 'vue';
import { parse, compileScript } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import { Link } from '@inertiajs/vue3';
import { route as ziggyRoute } from './vendor/tightenco/ziggy/dist/index.js';
import { Ziggy } from './resources/js/ziggy.js';

const scenario = JSON.parse(fs.readFileSync(0, 'utf8'));
const route = (name, params) => ziggyRoute(name, params, true, Ziggy);
const page = { props: { flash: {}, language: { messages: {} } } };
const stub = (props, { slots }) => props.show === false ? null : vue.h('div', [slots.header?.(), slots.default?.({ active: false })]);
const warnings = [];
async function render(file, props) {
    const { descriptor } = parse(fs.readFileSync('resources/js/' + file, 'utf8'));
    const compiled = compileScript(descriptor, { id: file, genDefaultAs: 'ContractPage', inlineTemplate: true });
    const bindings = { route, window: { innerWidth: 1440 } };
    let code = compiled.content.replace(/import\s+\{([\s\S]*?)\}\s+from\s+['"]([^'"]+)['"];?/g, (_, specifiers, module) => {
        for (const specifier of specifiers.split(',').filter(value => value.trim())) {
            const [name, alias = name] = specifier.trim().split(/\s+as\s+/);
            bindings[alias] = module === 'vue' ? vue[name]
                : name === 'Link' ? Link : name === 'Head' ? () => null
                : name === 'usePage' ? () => page
                : name === 'useForm' ? data => vue.reactive({ ...data, processing: false })
                : name === 'useDateFormatter' ? () => ({ formatDate: value => value, formatDateTime: value => value, formatCurrency: (value, currency) => `${value} ${currency}` })
                : name === 'router' ? {} : stub;
        }
        return '';
    }).replace(/import\s+(\w+)\s+from\s+['"][^'"]+['"];?/g, (_, name) => {
        bindings[name] = name === 'Checkbox' ? props => vue.h('input', { ...props, type: 'checkbox' }) : stub;
        return '';
    });
    code = stripTypeScriptTypes(code, { mode: 'strip' });
    const component = new Function(...Object.keys(bindings), code + '; return ContractPage;')(...Object.values(bindings));
    const app = vue.createSSRApp(component, props);
    app.config.globalProperties.$page = page;
    app.config.globalProperties.route = route;
    app.config.warnHandler = message => warnings.push(message);
    return renderToString(app);
}

if (scenario === 'original downloads') {
    const rows = ['document', 'invoice'].map((type, index) => ({ id: 7, file_id: index + 1, title: type,
        entity_type: type, file_name: `${type}.pdf`, size: 100, tags: [], shared_with_count: 0,
        file: { url: route('documents.serve', { guid: `source-${index}`, type: 'documents', extension: 'pdf' }), extension: 'pdf' } }));
    const html = await render('Pages/Documents/Index.vue', { documents: { data: rows, links: [] }, categories: [], filters: {} });
    for (const row of rows) {
        assert.ok(html.includes(`download="${row.file_name}"`));
        assert.ok(html.includes(row.file.url.replaceAll('&', '&amp;')));
    }
    const drawer = await render('Components/Domain/DocumentDrawer.vue', { document: rows[1], show: true });
    assert.ok(drawer.includes('download="invoice.pdf"'));
    assert.ok(!drawer.includes('/documents/7/download'));
}
if (scenario === 'category browsing') {
    const category = { id: 12, name: 'Garden & Plants', documents_count: 0, receipts_count: 1, color: '#123456', can_edit: false };
    const categories = await render('Pages/Documents/Categories.vue', { categories: [category] });
    assert.ok(categories.includes('/receipts?category_id=12'));
    assert.ok(categories.includes('View receipts (1)'));
    const documents = await render('Pages/Documents/Index.vue', { documents: { data: [], links: [] }, categories: [category], filters: { category: '12' } });
    assert.ok(documents.includes('No documents in Garden &amp; Plants'));
    assert.ok(documents.includes('All documents'));
    assert.ok(!documents.includes('Upload your first document'));
    page.props.language.messages = { receipts: 'Receipts', all_receipts: 'All Receipts', no_receipts_in_category: 'No receipts in :category', no_receipts_in_category_description: 'Choose All Receipts to clear the category filter.' };
    const receipts = await render('Pages/Receipt/Index.vue', { receipts: [], categories: [category], category });
    assert.ok(receipts.includes('No receipts in Garden &amp; Plants'));
    assert.ok(receipts.includes('All Receipts'));
    assert.ok(!receipts.includes('upload_first_receipts'));
}
if (scenario === 'vendor details') {
    const html = await render('Pages/Receipt/VendorShow.vue', { vendor: { name: 'DigitalOcean', description: 'Cloud hosting', contact_email: 'support@example.test' },
        items: { total: 1, data: [{ id: 1, text: 'Cloud subscription', qty: 1, price: 100, currency: 'NOK', receipt_id: 9, merchant: 'Store', receipt_date: '2026-10-06' }], links: [] } });
    for (const text of ['DigitalOcean', 'Vendor details', 'Cloud hosting', 'support@example.test', 'Cloud subscription', '100 NOK', '/receipts/9', 'All vendors']) {
        assert.ok(html.includes(text), text);
    }
    const optional = await render('Pages/Receipt/VendorShow.vue', { vendor: { name: 'DigitalOcean' },
        items: { total: 1, data: [{ id: 1, text: null, qty: null, price: null, receipt_id: 9 }], links: [] } });
    assert.ok(optional.includes('Unnamed item'));
    assert.ok(optional.includes('Receipt #9'));
    assert.ok(!optional.includes('undefined'));
}
assert.deepEqual(warnings, []);
JS;
    $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
    $process->setInput(json_encode($scenario));
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
})->with(['original downloads', 'category browsing', 'vendor details']);

it('resolves literal frontend route calls against the registered route inventory', function (): void {
    foreach (Filesystem::allFiles(resource_path('js')) as $file) {
        if (! in_array($file->getExtension(), ['vue', 'js', 'ts']) || $file->getFilename() === 'ziggy.js') {
            continue;
        }
        preg_match_all('/\broute\(\s*[\'"]([\w.-]+)[\'"]/', $file->getContents(), $calls);
        foreach ($calls[1] as $name) {
            expect(Route::has($name))->toBeTrue($file->getRelativePathname().': '.$name);
        }
    }
});

it('supplies shared document metadata and permission in the page contract', function (): void {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $category = Category::create(['user_id' => $owner->id, 'name' => 'Owned category', 'slug' => 'owned-category']);
    $file = File::factory()->create(['user_id' => $owner->id, 'fileName' => 'shared.pdf', 'fileSize' => 125]);
    $document = Document::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id, 'category_id' => $category->id]);
    $share = FileShare::create(['file_id' => $file->id, 'file_type' => 'document', 'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $recipient->id, 'permission' => 'edit', 'shared_at' => now()]);
    $response = $this->actingAs($recipient)->get(route('documents.shared'))->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('Documents/Shared')
        ->has('documents.data', 1)->where('documents.data.0.file_name', 'shared.pdf')
        ->where('documents.data.0.size', 125)->where('documents.data.0.category.name', 'Owned category')
        ->where('documents.data.0.owner.id', $owner->id)->where('documents.data.0.shared_permission', 'edit')
        ->where('documents.total', 1));
    $props = $response->viewData('page')['props'];
    $script = <<<'JS'
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
import { parse, compileScript } from '@vue/compiler-sfc';
import * as vue from 'vue';
import { renderToString } from '@vue/server-renderer';
import { route as ziggyRoute } from './vendor/tightenco/ziggy/dist/index.js';

const input = JSON.parse(fs.readFileSync(0, 'utf8'));
const { descriptor } = parse(fs.readFileSync('resources/js/Pages/Documents/Shared.vue', 'utf8'));
const compiled = compileScript(descriptor, { id: 'shared-contract', genDefaultAs: 'SharedPage', inlineTemplate: true });
const route = (name, params) => ziggyRoute(name, params, true, input.ziggy);
const bindings = { route };
const stub = (props, { slots }) => vue.h('div', slots.default?.());
let code = compiled.content.replace(/import\s+\{([\s\S]*?)\}\s+from\s+['"]([^'"]+)['"];?/g, (_, specifiers, module) => {
    for (const specifier of specifiers.split(',')) {
        const [name, alias = name] = specifier.trim().split(/\s+as\s+/);
        bindings[alias] = module === 'vue' ? vue[name] : name === 'useDateFormatter' ? () => ({ formatDate: value => value }) : name === 'Head' ? () => null : name === 'router' ? { get() {} } : stub;
    }
    return '';
}).replace(/import\s+(\w+)\s+from\s+['"][^'"]+['"];?/g, (_, name) => {
    bindings[name] = stub;
    return '';
});
code = stripTypeScriptTypes(code, { mode: 'strip' });
const component = new Function(...Object.keys(bindings), code + '; return SharedPage;')(...Object.values(bindings));
const warnings = [];
async function render(props) {
    const app = vue.createSSRApp(component, props);
    app.config.globalProperties.$page = { props };
    app.config.globalProperties.route = route;
    app.config.warnHandler = message => warnings.push(message);
    return renderToString(app);
}
const html = await render(input.props);
assert.ok(html.includes('Can edit'));
assert.ok(html.includes('Owned category'));
assert.ok(html.includes('125 Bytes'));
assert.ok(html.includes('/documents/' + input.props.documents.data[0].id));
const paged = await render({ ...input.props, documents: { ...input.props.documents, total: 21, from: 1, to: 20,
    links: [...input.props.documents.links, { url: route('documents.shared', { page: 2 }), label: '2', active: false }] } });
assert.ok(paged.includes('Showing'));
assert.ok(paged.includes('21'));
const empty = await render({ ...input.props, documents: { ...input.props.documents, data: [], total: 0 } });
assert.ok(empty.includes('No shared documents yet'));
assert.deepEqual(warnings, []);
JS;
    $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
    $process->setInput(json_encode(['props' => $props, 'ziggy' => (new Ziggy)->toArray()]));
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
    $this->actingAs(User::factory()->create())->get(route('documents.shared'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('documents.data', 0));
    $share->update(['expires_at' => now()->subMinute()]);
    $this->actingAs($recipient)->get(route('documents.shared'))->assertInertia(fn (AssertableInertia $page) => $page->has('documents.data', 0));
});

it('resolves expiry scanner duplicate and review copy with locale fallback and day counts', function (string $locale, string $day, string $days): void {
    Lang::get('messages', [], 'en');
    Lang::addLines(['messages.test_english_fallback' => 'English fallback'], 'en');
    $response = $this->actingAs(User::factory()->create())->withSession(['locale' => $locale])->get(route('dashboard'))->assertOk();
    $messages = $response->viewData('page')['props']['language']['messages'];
    expect($messages['test_english_fallback'])->toBe('English fallback');
    foreach (['expiring_vouchers', 'ending_warranties', 'view_all', 'no_expiring_vouchers', 'no_ending_warranties', 'scanner_preferences', 'ignore_duplicate', 'needs_review'] as $key) {
        expect($messages[$key])->not->toBe($key);
    }
    $script = <<<'JS'
import assert from 'node:assert/strict';
import fs from 'node:fs';
const input = JSON.parse(fs.readFileSync(0, 'utf8'));
const source = fs.readFileSync('resources/js/Composables/useTranslations.js', 'utf8')
    .replace(/import[^;]+;/, '').replace('export function', 'function');
const useTranslations = new Function('usePage', source + '; return useTranslations;')(() => ({ props: { language: { messages: input.messages } } }));
const { __ } = useTranslations();
assert.equal(__('expiring_within_days', { count: 1 }), input.day);
assert.equal(__('expiring_within_days', { count: 30 }), input.days);
assert.equal(__('test_english_fallback'), 'English fallback');
JS;
    $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
    $process->setInput(json_encode(compact('messages', 'day', 'days')));
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
})->with([
    ['en', 'Expiring within 1 day', 'Expiring within 30 days'],
    ['nb', 'Utløper innen 1 dag', 'Utløper innen 30 dager'],
]);
