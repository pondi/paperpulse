<?php

use App\Models\Category;
use App\Models\Document;
use App\Models\File;
use App\Models\FileShare;
use App\Models\User;
use App\Services\PulseDavService;
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

const input = JSON.parse(fs.readFileSync(0, 'utf8'));
const scenario = input.scenario;
const route = (name, params) => ziggyRoute(name, params, true, input.ziggy);
const page = { props: { flash: {}, auth: { user: { preferences: { timezone: 'UTC' } } }, language: { messages: {} } } };
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
                : name === 'useForm' ? data => vue.reactive({ ...data, processing: false, errors: {} })
                : name === 'useDateFormatter' ? () => ({ formatDate: value => value, formatDateTime: value => value, formatCurrency: (value, currency) => `${value} ${currency}` })
                : name === 'useTranslations' ? () => ({ __: key => key })
                : name === 'router' ? {} : stub;
        }
        return '';
    }).replace(/import\s+(\w+)\s+from\s+['"][^'"]+['"];?/g, (_, name) => {
        bindings[name] = name === 'Checkbox' ? props => vue.h('input', { ...props, type: 'checkbox' }) : stub;
        return '';
    });
    code = stripTypeScriptTypes(code.replaceAll('import.meta.env.DEV', 'false'), { mode: 'strip' });
    const component = new Function(...Object.keys(bindings), code + '; return ContractPage;')(...Object.values(bindings));
    const app = vue.createSSRApp(component, props);
    app.config.globalProperties.$page = page;
    app.config.globalProperties.route = route;
    app.config.warnHandler = message => warnings.push(message);
    const context = {};
    const html = await renderToString(app, context);
    return html + Object.values(context.teleports || {}).join('');
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
if (scenario === 'row names') {
    for (const id of [1, 2]) {
        const card = await render('Components/Search/SearchResultCard.vue', { result: { id, type: 'receipt', title: 'Same store', url: route('receipts.show', id), tags: [], items: [] } });
        assert.ok(card.includes(`aria-label="Select receipt Same store (#${id})"`));
        assert.ok(card.includes(`aria-label="Open receipt Same store (#${id}) in new tab"`));
    }
    const rows = [1, 2].map(id => ({ id, file_id: id, title: 'Named source', file_name: 'source.pdf', size: 100, tags: [] }));
    const documents = await render('Pages/Documents/Index.vue', { documents: { data: rows, links: [] }, categories: [], filters: {} });
    for (const id of [1, 2]) {
        assert.ok(documents.includes(`aria-label="Select Named source (file #${id})"`));
    }
    const receipts = await render('Pages/Receipt/Index.vue', { receipts: [1, 2].map(id => ({ id, merchant: { name: 'Same store' }, tags: [], total_amount: 100, currency: 'NOK' })), categories: [] });
    for (const id of [1, 2]) {
        assert.ok(receipts.includes(`aria-label="Select receipt #${id} from Same store"`));
    }
    assert.ok(receipts.includes('aria-label="Select all receipts"'));
}
if (scenario === 'recommendation states') {
    for (const status of ['queued', 'running', 'failed', 'completed', 'awaiting_decisions']) {
        const data = status === 'awaiting_decisions' ? [{ id: 1, status: 'pending', current_paths: ['Building'], proposed_path: 'Home', affected_count: 1, confidence: 0.95, reason: 'Rename Building to Home', preview_items: [] }] : [];
        const html = await render('Pages/Collections/Recommendations.vue', { run: { id: 1, status, attempts: 1, created_at: '2026-10-06', started_at: '2026-10-06', updated_at: '2026-10-06' },
            recommendations: { data, last_page: 1 }, pending_count: data.length, enabled: true, can_start: false });
        assert.ok(html.includes('Archive folder review'));
        if (status === 'failed') {
            assert.ok(html.includes('Folder recommendation generation failed'));
            assert.ok(html.includes('Dismiss failed run'));
            assert.ok(!html.includes('No folder changes to review'));
        } else if (status === 'completed') {
            assert.ok(html.includes('No folder changes to review'));
        } else if (status === 'awaiting_decisions') {
            assert.ok(html.includes('Rename Building to Home'));
        } else {
            assert.ok(html.includes('Your recommendations are being prepared.'));
            assert.ok(!html.includes('No folder changes to review'));
        }
    }
}
if (scenario === 'folder content priority') {
    for (const populated of [false, true]) {
        const html = await render('Pages/Collections/Show.vue', {
            collection: { id: 1, name: 'Building', color: '#123456', icon: 'folder', files: populated ? [{ id: 5, fileName: 'contract.pdf', created_at: '2026-10-06' }] : [] },
            stats: { total_files: populated ? 1 : 0, documents_count: populated ? 1 : 0, receipts_count: 0 },
            children: { data: [{ id: 2, name: 'Contracts' }] }, isOwner: true
        });
        assert.ok(html.includes('Contracts'));
        assert.ok(html.indexOf('Subfolders') < html.indexOf('<details'));
        assert.ok(html.indexOf('Files in this Collection') < html.indexOf('<details'));
        assert.ok(html.includes('<summary class="cursor-pointer'));
        assert.ok(!html.includes('<details open'));
        assert.ok(!html.includes('sm:grid-cols-3'));
        assert.ok(html.includes(populated ? 'contract.pdf' : 'No files directly in this collection'));
    }
}
if (scenario === 'subfolder context') {
    const html = await render('Pages/Collections/Index.vue', { collections: { data: [] }, filters: { parent_id: 3 },
        breadcrumbs: [{ label: 'Building', href: route('collections.show', 1) }, { label: 'Contracts', href: route('collections.show', 2) }, { label: 'Hønsfaret', href: route('collections.show', 3) }] });
    for (const text of ['Subfolders in Hønsfaret', 'No subfolders in Hønsfaret', 'Create Subfolder', 'Back to Hønsfaret', '/collections/3']) assert.ok(html.includes(text), text);
    const root = await render('Pages/Collections/Index.vue', { collections: { data: [] }, filters: {}, breadcrumbs: [] });
    assert.ok(root.includes('No collections'));
    assert.ok(root.includes('Create Collection'));
    assert.ok(!root.includes('No subfolders'));
}
if (scenario === 'settings sections') {
    const html = await render('Pages/Preferences/Index.vue', { preferences: {}, categories: [], options: {}, timezones: [], organizationAliases: [] });
    assert.ok(html.includes('aria-label="Settings sections"'));
    for (const id of ['general', 'display', 'notifications', 'processing', 'scanner', 'organization', 'archive']) {
        assert.ok(html.includes(`href="#preferences-${id}"`), id);
        assert.ok(html.includes(`id="preferences-${id}"`), id);
    }
    assert.ok(html.indexOf('id="preferences-general"') < html.indexOf('id="preferences-organization"'));
}
if (scenario === 'invoice native line values') {
    const html = await render('Pages/Invoices/Show.vue', { invoice: { id: 1, invoice_number: 'NATIVE-1', currency: 'EUR', payment_status: 'unpaid', total_amount: 42, tags: [], collections: [], shared_users: [], file: null,
        line_items: [{ id: 1, description: 'Zero values', quantity: 1, unit_price: 0, tax_rate: 0, total_amount: 0 },
            { id: 2, description: 'Missing values', quantity: 1, unit_price: null, tax_rate: null, total_amount: null },
            { id: 3, description: 'Native values', quantity: 1, unit_price: '42.00', tax_rate: '25.00', total_amount: '42.00', converted_unit_price: null }] }, available_tags: [] });
    assert.ok(html.includes('0 EUR'));
    assert.ok(html.includes('0%'));
    assert.ok(html.includes('42.00 EUR'));
    assert.ok(html.includes('25.00%'));
    assert.equal((html.match(/Not recorded/g) || []).length, 3);
    assert.ok(!html.includes('Conversion unavailable'));
    assert.ok(!html.includes('>%<'));
}
if (scenario === 'report navigation') {
    for (const status of ['completed', 'failed', 'needs_review']) {
        const html = await render('Pages/Files/ExtractionReport.vue', { report: {
            file: { id: 42, name: 'source.pdf', status, file_type: 'invoice' }, classification: {}, coverage: {}, review: {}, failure: {},
            extraction: { has_extraction_issues: status !== 'completed', validation_warnings: [] },
            entities: [{ id: 9, type: 'invoice', is_primary: true, confidence_score: 0.95 }] } });
        assert.ok(html.includes('/invoices/9'));
        assert.ok(html.includes('invoice #9'));
        assert.ok(html.includes('/files-processing?file_id=42'));
        assert.ok(html.includes('/files/42'));
        assert.ok(html.includes('Open file workspace'));
    }
}
if (scenario === 'pdf initial fit') {
    for (const [file, props] of [
        ['Components/Domain/DocumentPreview.vue', { file: { id: 1, name: 'Scan', pdfUrl: '/scan.pdf?variant=original', url: '/scan.pdf', extension: 'pdf' } }],
        ['Components/Common/PdfViewer.vue', { show: true, pdfUrl: '/scan.pdf?variant=original' }],
        ['Components/Common/FilePreviewModal.vue', { show: true, item: { title: 'Scan', type: 'invoice', file_id: 1, file: { pdfUrl: '/scan.pdf?variant=original' } } }]
    ]) {
        const html = await render(file, props);
        assert.ok(html.includes('/scan.pdf?variant=original#navpanes=0&amp;view=Fit'), file);
    }
    const image = await render('Components/Domain/DocumentPreview.vue', { file: { id: 1, name: 'Portrait', url: '/scan.jpg', extension: 'jpg' } });
    assert.ok(image.includes('src="/scan.jpg"'));
    assert.ok(!image.includes('<iframe'));
}
if (scenario === 'scanner onboarding') {
    for (const enabled of [true, false]) {
        const html = await render('Pages/PulseDav/Index.vue', { files: { data: [], links: [] }, tags: [], scannerImportsEnabled: enabled });
        for (const text of ['No scanner imports yet', 'Scanner connection instructions', 'WebDAV server address', 'verified PaperPulse email address', 'private scanner inbox', '/preferences#preferences-scanner']) assert.ok(html.includes(text), text);
        assert.ok(html.includes(enabled ? 'Scanner imports are enabled' : 'Scanner imports are not configured on this server'));
        assert.equal(/<button[^>]*\sdisabled(?:=|[\s>])/.test(html), !enabled);
    }
}
if (scenario === 'mobile dashboard amounts') {
    const html = await render('Pages/Dashboard.vue', { expiringVouchers: { items: [], total: 0 }, endingWarranties: { items: [], total: 0 },
        recentReceipts: [{ id: 5, merchant: { name: 'Long merchant name' }, receipt_date: '2026-10-06', total_amount: 42, currency: 'EUR', receipt_category: 'Groceries' }] });
    assert.ok(html.includes('Long merchant name'));
    assert.ok(html.includes('42 EUR'));
    assert.ok(html.includes('Groceries'));
    assert.ok(html.includes('max-w-[10rem] break-words'));
    assert.equal((html.match(/hidden sm:table-cell/g) || []).length, 4);
    assert.ok(html.includes('2026-10-06'));
}
if (scenario === 'receipt totals review') {
    const reconciliation = { calculated_total: '22.70', discount_amount: '1.14', tip_amount: '0.00', total_amount: '21.56', needs_review: false };
    const html = await render('Pages/Files/ExtractionReport.vue', { report: { file: { id: 391, name: 'Wine.pdf', status: 'needs_review', file_type: 'receipt' },
        classification: {}, extraction: { has_extraction_issues: true, validation_warnings: [] }, coverage: {},
        review: { reason: 'receipt_totals' }, reconciliation, receipt_currency: 'EUR', failure: {}, entities: [{ type: 'receipt', id: 1246 }] } });
    for (const text of ['22.70 EUR', '1.14 EUR', '21.56 EUR', 'Confirm reconciled totals', 'checked the extracted values against the source', '/receipts/1246', 'does not rerun extraction']) assert.ok(html.includes(text), text);
    const receipt = await render('Pages/Receipt/Show.vue', { receipt: { id: 1246, file_id: 391, file: { id: 391, needs_review: true }, currency: 'EUR', total_amount: '21.56', reconciliation, lineItems: [] }, categories: [] });
    for (const text of ['Source discount', '1.14 EUR', 'Final amount', '21.56 EUR', '/files/391/extraction-report']) assert.ok(receipt.includes(text), text);
}
assert.deepEqual(warnings, []);
JS;
    $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
    $process->setInput(json_encode(['scenario' => $scenario, 'ziggy' => (new Ziggy)->toArray()]));
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
})->with(['original downloads', 'category browsing', 'vendor details', 'row names', 'recommendation states', 'folder content priority', 'subfolder context', 'settings sections', 'invoice native line values', 'report navigation', 'pdf initial fit', 'scanner onboarding', 'mobile dashboard amounts', 'receipt totals review']);

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

it('shares scanner import configuration without exposing connection secrets', function (bool $enabled, ?string $bucket, bool $expected): void {
    config(['services.pulsedav.auth_enabled' => $enabled, 'filesystems.disks.pulsedav.bucket' => $bucket]);
    $this->mock(PulseDavService::class);
    $response = $this->actingAs(User::factory()->create())->get(route('pulsedav.index'))->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->has('files.data', 0)
        ->where('scannerImportsEnabled', $expected)->missing('scannerCredentials'));
})->with([[true, 'private-incoming', true], [false, 'private-incoming', false], [true, null, false]]);
