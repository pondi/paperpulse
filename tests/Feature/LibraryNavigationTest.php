<?php

use Symfony\Component\Process\Process;
use Tighten\Ziggy\Ziggy;

it('renders usable navigation for library organization reports and processing workspaces', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import fs from 'node:fs';
import * as vue from 'vue';
import { parse, compileScript } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import { route as ziggyRoute } from './vendor/tightenco/ziggy/dist/index.js';
import * as navigation from './resources/js/utils/libraryNavigation.js';

const ziggy = JSON.parse(fs.readFileSync(0, 'utf8'));
let current = '';
const page = { url: '/library', props: { auth: { user: { is_admin: false } }, navigation: {
    attention_count: 2, saved_views: [{ id: 10, name: 'Monthly receipts' }]
} } };
const route = (name, params) => name ? ziggyRoute(name, params, true, ziggy) : { current: () => current };
const Link = (props, { attrs, slots }) => vue.h('a', attrs, slots.default?.());
function component(path) {
    const { descriptor } = parse(fs.readFileSync(path, 'utf8'));
    const compiled = compileScript(descriptor, { id: path, genDefaultAs: 'Component', inlineTemplate: true });
    const bindings = { route };
    const code = compiled.content.replace(/import\s+\{([\s\S]*?)\}\s+from\s+['"]([^'"]+)['"];?/g, (_, names, module) => {
        for (const specifier of names.split(',')) {
            const [name, alias = name] = specifier.trim().split(/\s+as\s+/);
            bindings[alias] = module === 'vue' ? vue[name] : module.includes('libraryNavigation') ? navigation[name] :
                name === 'usePage' ? () => page : name === 'Link' ? Link : () => vue.h('span');
        }
        return '';
    }).replace(/import\s+(\w+)\s+from\s+['"][^'"]+['"];?/g, (_, name) => {
        bindings[name] = () => vue.h('span');
        return '';
    });
    return new Function(...Object.keys(bindings), code + '; return Component;')(...Object.values(bindings));
}
const sidebar = component('resources/js/Components/Navigation/AppSidebar.vue');
const workspace = component('resources/js/Components/Navigation/WorkspaceNavigation.vue');
async function render(component) {
    const app = vue.createSSRApp(component);
    app.config.globalProperties.route = route;
    return renderToString(app);
}
for (const [name, active, tab] of [
    ['library.index', 'Library', 'Invoices'], ['documents.show', 'Library', 'Documents'],
    ['documents.categories', 'Collections', 'Categories'], ['tags.index', 'Collections', 'Tags'],
    ['categories.index', 'Collections', 'Categories'],
    ['analytics.index', 'Reports', 'Analytics'], ['exports.index', 'Reports', 'Exports'],
    ['files.index', 'Activity', 'Processing'], ['pulsedav.index', 'Imports', 'Scanner imports'],
]) {
    current = name;
    page.props.filters = name === 'library.index' ? { type: 'invoice' } : undefined;
    const main = await render(sidebar);
    assert.match(main, new RegExp('aria-current="page"[^>]*>[\\s\\S]*?' + active));
    const tabs = await render(workspace);
    assert.ok(tabs.includes(tab), name);
    assert.ok(tabs.includes('aria-current="page"'), name);
    assert.ok(!tabs.includes('Job status'), name);
}
current = 'library.index';
page.url = '/library?view=unpaid&saved_search=10';
const saved = await render(sidebar);
assert.equal((saved.match(/aria-current="page"/g) || []).length, 1);
assert.match(saved, /aria-current="page"[^>]*>[\s\S]*?Monthly receipts/);
page.url = '/library?view=recent&saved_search=10';
assert.equal(((await render(sidebar)).match(/aria-current="page"/g) || []).length, 1);
current = 'search';
page.url = '/search?saved_search=10';
assert.equal(((await render(sidebar)).match(/aria-current="page"/g) || []).length, 1);
current = 'library.index';
page.url = '/library?view=all';
assert.equal(((await render(sidebar)).match(/aria-current="page"/g) || []).length, 1);
current = 'files.index';
page.props.auth.user.is_admin = true;
assert.ok((await render(workspace)).includes('Job status'));
current = 'documents.show';
page.props.document = { file_id: 123 };
assert.ok((await render(workspace)).includes('/files/123'));
JS;
    $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
    $process->setInput(json_encode((new Ziggy)->toArray()));
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});
