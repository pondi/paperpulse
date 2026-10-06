<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;

it('renders the scanner page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('scanner'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scanner/Index')
        );
});

it('creates and selects scanner import tags with one request', function (): void {
    $process = new Process(['node', '--input-type=module', '--eval', <<<'JS'
import assert from 'node:assert/strict';
import fs from 'node:fs';
import * as vue from 'vue';
import { parse } from '@vue/compiler-sfc';
const parent = parse(fs.readFileSync('resources/js/Pages/PulseDav/Index.vue', 'utf8')).descriptor;
assert.ok(!parent.template.content.includes('@create-tag'));
const { descriptor } = parse(fs.readFileSync('resources/js/Components/Domain/TagSelector.vue', 'utf8'));
const script = descriptor.scriptSetup.content.replace(/^import .*;?$/gm, '');
const props = { modelValue: [3], allowCreate: true };
const requests = [];
const events = [];
const bindings = { ...vue, onMounted: () => {}, defineExpose: () => {}, defineProps: () => props,
    defineEmits: () => (name, value) => { events.push([name, value]); if (name === 'update:modelValue') props.modelValue = value; },
    route: name => name, axios: { post: async (url, data) => { requests.push([url, data]); return { data: { id: 8, name: data.name } }; } } };
const selector = new Function(...Object.keys(bindings), script + '; return { searchQuery, createNewTag, selectTag };')(...Object.values(bindings));
selector.searchQuery.value = ' Travel ';
await selector.createNewTag();
assert.deepEqual(requests, [['tags.store', { name: 'Travel' }]]);
assert.deepEqual(props.modelValue, [3, 8]);
selector.selectTag({ id: 9, name: 'Existing' });
assert.deepEqual(props.modelValue, [3, 8, 9]);
assert.equal(requests.length, 1);
JS], base_path());
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});
