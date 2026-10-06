<?php

use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('keeps only the latest search state and permits recovery after search failures', function () {
    $script = <<<'JS'
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';

const source = fs.readFileSync('resources/js/Pages/Search.vue', 'utf8');
const { descriptor } = parse(source);
compileScript(descriptor, { id: 'search-state-test' });
assert.deepEqual(compileTemplate({ source: descriptor.template.content, filename: 'Search.vue', id: 'search-state-test' }).errors, []);
const requests = [];
const watchers = new Map();
const timers = new Map();
let timerId = 0;
let unmount;
const history = [];
const context = {
  ref: value => ({ value }),
  computed: getter => ({ get value() { return getter(); } }),
  watch: (target, callback) => watchers.set(target, callback),
  onMounted: () => {},
  onBeforeUnmount: callback => { unmount = callback; },
  defineProps: () => ({ query: 'initial', initialResults: [], initialFacets: {}, initialPagination: { page: 1, total: 0, last_page: 0 }, initialSearchStatus: 'available' }),
  axios: { get: (url, options) => new Promise((resolve, reject) => requests.push({ resolve, reject, ...options })) },
  AbortController,
  console,
  router: { replace: options => history.push(options) },
  route: () => '/search',
  setTimeout: callback => { timers.set(++timerId, callback); return timerId; },
  clearTimeout: id => timers.delete(id),
};
vm.createContext(context);
vm.runInContext(descriptor.scriptSetup.content.replace(/^import .*;$/gm, '') + '\nglobalThis.state = { performSearch, searchQuery, filters, results, searching, searchError, facets, pagination, hasActiveFilters, clearFilters, sortBy, sortedResults };', context);
const state = context.state;
state.results.value = [{ id: 'larger', total: '1234.56' }, { id: 'smaller', total: '999.00' }];
state.sortBy.value = 'amount_asc';
assert.equal(state.sortedResults.value[0].id, 'smaller');
state.sortBy.value = 'amount_desc';
assert.equal(state.sortedResults.value[0].id, 'larger');
state.results.value = [];
state.sortBy.value = 'relevance';
const data = (id, status = 'available') => ({ data: { results: id ? [{ id }] : [], facets: { total: id ? 1 : 0 }, pagination: { page: 1, last_page: id ? 1 : 0, total: id ? 1 : 0 }, search_status: status } });
const flush = async () => { await Promise.resolve(); await Promise.resolve(); };

const old = state.performSearch();
state.filters.value.category = 'new category';
watchers.get(state.filters)();
assert.equal(requests[0].signal.aborted, true);
requests[0].resolve(data('old'));
await old;
assert.equal(state.searching.value, true);
assert.equal(state.results.value.length, 0);
requests[1].resolve(data('new'));
await flush();
assert.equal(state.results.value[0].id, 'new');
assert.equal(state.searching.value, false);

const staleError = state.performSearch();
const current = state.performSearch();
requests[3].resolve(data('current'));
await current;
requests[2].reject(new Error('Old failure'));
await staleError;
assert.equal(state.results.value[0].id, 'current');
assert.equal(state.searchError.value, '');

const unavailable = state.performSearch();
requests[4].resolve(data(null, 'unavailable'));
await unavailable;
assert.match(state.searchError.value, /unavailable/);
const retry = state.performSearch();
requests[5].resolve(data('recovered'));
await retry;
assert.equal(state.searchError.value, '');
assert.equal(state.results.value[0].id, 'recovered');
const partial = state.performSearch();
requests[6].resolve(data('partial', 'partial'));
await partial;
assert.match(state.searchError.value, /Some search results/);
assert.equal(state.results.value[0].id, 'partial');

const failed = state.performSearch();
requests[7].reject(new Error('Network down'));
await failed;
assert.match(state.searchError.value, /unavailable/);
assert.equal(state.searching.value, false);
const invalid = state.performSearch();
requests[8].reject({ response: { status: 422 } });
await invalid;
assert.match(state.searchError.value, /filters/);

const beforeClear = state.performSearch();
state.searchQuery.value = '';
state.filters.value.category = '';
watchers.get(state.searchQuery)();
assert.equal(requests[9].signal.aborted, true);
requests[9].resolve(data('obsolete'));
await beforeClear;
assert.equal(state.results.value.length, 0);
assert.equal(state.searching.value, false);
assert.equal(state.pagination.value.total, 0);
await state.performSearch();
assert.equal(history.at(-1).props({}).query, '');
assert.equal(history.at(-1).props({}).initialResults.length, 0);

state.filters.value.tags = ['Travel'];
assert.ok(state.hasActiveFilters.value);
const tagged = state.performSearch();
assert.deepEqual(Array.from(requests[10].params.tags), ['Travel']);
requests[10].resolve(data('tagged'));
await tagged;
assert.deepEqual(Array.from(history.at(-1).props({}).initialFilters.tags), ['Travel']);
delete state.filters.value.tags;
state.filters.value = { type: 'invoice', date_from: '2025-01-01', date_to: '2026-01-01', amount_min: 0, amount_max: 100, category: 'mat', collection_id: 12, tags: ['Travel'], document_type: 'plan', vendor: 'store', vendors: ['store'] };
state.sortBy.value = 'date_desc';
state.searchQuery.value = 'intentional query';
state.clearFilters();
assert.ok(!state.hasActiveFilters.value);
assert.equal(state.searchQuery.value, 'intentional query');
assert.equal(state.sortBy.value, 'relevance');
assert.equal(state.filters.value.amount_min, null);
assert.equal(state.filters.value.amount_max, null);
assert.equal(state.filters.value.type, 'all');
assert.equal(state.filters.value.collection_id, '');

state.searchQuery.value = 'one';
watchers.get(state.searchQuery)();
state.searchQuery.value = 'two';
watchers.get(state.searchQuery)();
assert.equal(timers.size, 1);
const latest = state.performSearch();
assert.equal(timers.size, 0);
watchers.get(state.searchQuery)();
unmount();
assert.equal(timers.size, 0);
assert.equal(requests[11].signal.aborted, true);
requests[11].resolve(data('unmounted'));
await latest;
assert.equal(state.results.value.length, 0);
JS;
    $process = new Process(['node', '--input-type=module', '-e', $script], base_path());
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});
