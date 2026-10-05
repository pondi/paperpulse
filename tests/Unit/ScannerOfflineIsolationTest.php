<?php

use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('keeps authenticated scanner state out of offline caches across accounts', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const handlers = {};
const stores = new Map([['paperpulse-scanner-v1', new Map([['https://paperpulse.test/scanner', 'user A session']])], ['unrelated-cache', new Map()]]);
const cacheApi = {
  keys: async () => [...stores.keys()],
  delete: async name => stores.delete(name),
  open: async name => {
    if (!stores.has(name)) stores.set(name, new Map());
    return {
      put: async (request, response) => stores.get(name).set(request.url, response),
      match: async request => stores.get(name).get(request.url)?.clone(),
    };
  },
};
let online = true;
let response = new Response('public script', { headers: { 'Content-Type': 'application/javascript' } });
const context = vm.createContext({
  URL, Request, Response, caches: cacheApi,
  self: { location: { origin: 'https://paperpulse.test' }, addEventListener: (name, handler) => handlers[name] = handler, skipWaiting: async () => {}, clients: { claim: async () => {} } },
  fetch: async request => {
    if (!online) throw new Error('offline');
    if (request.url.includes('/vendor/')) assert.equal(request.credentials, 'omit');
    return response.clone();
  },
});
vm.runInContext(fs.readFileSync(process.argv[1] + '/public/sw-scanner.js', 'utf8'), context);
const lifecycle = async name => {
  let pending;
  handlers[name]({ waitUntil: promise => pending = promise });
  await pending;
};
const request = async (path, options = {}) => {
  let pending;
  const url = 'https://paperpulse.test' + path;
  const input = options.mode === 'navigate' ? { url, method: 'GET', mode: 'navigate' } : new Request(url, options);
  handlers.fetch({ request: input, respondWith: promise => pending = promise });
  return pending;
};
await lifecycle('install');
await lifecycle('activate');
assert.equal(stores.has('paperpulse-scanner-v1'), false);
assert.equal(stores.has('unrelated-cache'), true);
response = new Response('user B session', { headers: { 'Content-Type': 'text/html' } });
assert.equal(await (await request('/scanner', { mode: 'navigate' })).text(), 'user B session');
assert.equal([...stores.values()].some(cache => cache.has('https://paperpulse.test/scanner')), false);
online = false;
const offline = await request('/scanner', { mode: 'navigate' });
assert.equal(offline.status, 503);
assert.match(await offline.text(), /Connect to the internet/);
for (const path of ['/api/v1/files', '/dashboard', '/documents/secret', '/vendor/private', '/build/private']) {
  assert.equal(await request(path), undefined);
}
let intercepted = false;
handlers.fetch({ request: new Request('https://foreign.test/vendor/opencv.js'), respondWith: () => intercepted = true });
assert.equal(intercepted, false);
assert.equal(await request('/vendor/opencv.js?v=2', { method: 'POST' }), undefined);
online = true;
response = new Response('public script', { headers: { 'Content-Type': 'application/javascript' } });
await request('/vendor/opencv.js?v=2');
online = false;
assert.equal(await (await request('/vendor/opencv.js?v=2')).text(), 'public script');
online = true;
response = new Response('user C login page', { headers: { 'Content-Type': 'text/html' } });
await request('/vendor/opencv.js?v=2');
online = false;
assert.equal(await (await request('/vendor/opencv.js?v=2')).text(), 'public script');
const layout = fs.readFileSync(process.argv[1] + '/resources/js/Layouts/AuthenticatedLayout.vue', 'utf8');
const logout = layout.match(/const clearScannerCache = async \(\) => \{[\s\S]*?\n\};/)[0];
context.window = { caches: cacheApi };
vm.runInContext(logout, context);
await vm.runInContext('clearScannerCache()', context);
assert.equal([...stores.keys()].some(name => name.startsWith('paperpulse-scanner-')), false);
assert.equal(stores.has('unrelated-cache'), true);
JS;

    $process = new Process(['node', '--input-type=module', '-e', $script, base_path()]);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});
