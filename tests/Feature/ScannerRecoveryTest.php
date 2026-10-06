<?php

use Symfony\Component\Process\Process;

it('recovers scanner failures without retaining streams matrices timers or drag listeners', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse, compileScript } from '@vue/compiler-sfc';
import { ref, computed, watch, nextTick } from 'vue';

const timers = new Map();
let timerId = 0;
const setTimer = (fn, delay) => { timers.set(++timerId, { fn, delay }); return timerId; };
const clearTimer = id => timers.delete(id);
const timeout = delay => {
    const entry = [...timers].find(([, timer]) => timer.delay === delay);
    assert.ok(entry, `Missing ${delay}ms timeout`);
    timers.delete(entry[0]);
    entry[1].fn();
};
const target = () => {
    const listeners = new Map();
    return {
        listeners,
        addEventListener: (type, fn) => listeners.set(type, fn),
        removeEventListener: type => listeners.delete(type),
        dispatch: type => listeners.get(type)?.(),
    };
};
const scripts = [];
const document = {
    ...target(),
    body: { appendChild: script => scripts.push(script) },
    createElement: tag => tag === 'script'
        ? { remove() { this.removed = true; } }
        : { getContext: () => ({ drawImage() {} }), toDataURL: () => 'image' },
};
let fault;
const resources = [];
class Mat {
    constructor() { this.rows = this.cols = 100; this.deleted = 0; resources.push(this); }
    delete() { this.deleted++; }
}
const operation = name => { if (fault === name) throw new Error(name + ' failed'); };
const cv = {
    Mat,
    MatVector: class extends Mat { size() { return 2; } get() { return new Mat(); } },
    Size: class {}, Scalar: class {},
    imread: () => new Mat(),
    cvtColor: () => operation('color'), GaussianBlur() {}, Canny() {},
    getStructuringElement: () => new Mat(), morphologyEx() {}, findContours() {},
    contourArea: () => { operation('contour'); return 8000; }, arcLength: () => 100,
    approxPolyDP: (contour, approx) => {
        operation('approx'); approx.rows = 4; approx.data32S = [0, 0, 100, 0, 100, 100, 0, 100];
    },
    matFromArray: () => new Mat(), getPerspectiveTransform: () => new Mat(),
    warpPerspective: () => operation('warp'), imshow() {},
};
const window = { cv: null };
let mediaRequest;
const navigator = { mediaDevices: { getUserMedia: () => mediaRequest() } };
let callbacks;
let visits = 0;
const router = {
    post: (url, data, options) => {
        callbacks = options;
        options.onCancelToken({ cancel() { options.onCancel(); options.onFinish(); } });
    },
    visit: () => visits++,
};
const unmounted = [];
const bindings = {
    ref, computed, watch, nextTick, onMounted() {}, onUnmounted: fn => unmounted.push(fn),
    document, window, navigator, cv, router, route: name => name,
    console: { error() {}, log() {} },
    setTimeout: setTimer, clearTimeout: clearTimer,
    Link: {}, XMarkIcon: {}, PencilSquareIcon: {}, ExclamationTriangleIcon: {}, SparklesIcon: {},
    Toast: {}, PerspectiveCropper: {}, CollectionSelector: {}, TagSelector: {},
    jsPDF: class { addImage() {} output() { return new Blob(['pdf']); } },
};
const setup = (file, name, props = {}, emit = () => {}) => {
    const { descriptor } = parse(readFileSync(file, 'utf8'));
    const compiled = compileScript(descriptor, { id: name, genDefaultAs: name });
    const code = compiled.content.replace(/^import .+$/gm, '');
    const component = new Function(...Object.keys(bindings), code + `; return ${name};`)(...Object.values(bindings));
    return component.setup(props, { expose() {}, emit });
};
const flush = async () => { for (let i = 0; i < 5; i++) await Promise.resolve(); };
const page = setup('resources/js/Pages/Scanner/Index.vue', 'ScannerPage');

// Blocked scripts and delayed runtimes can retry, and old handlers are removed.
page.loadOpenCV();
scripts[0].onerror();
assert.equal(page.cvLoaded.value, false);
assert.match(page.error.value, /could not load/);
assert.equal(scripts[0].removed, true);
assert.equal(scripts[0].onload, null);
page.loadOpenCV();
timeout(15000);
assert.equal(scripts[1].removed, true);
page.loadOpenCV();
window.cv = cv;
scripts[2].onload();
cv.getBuildInformation = () => 'ready';
cv.onRuntimeInitialized();
assert.equal(page.cvLoaded.value, true);
assert.equal(cv.onRuntimeInitialized, null);
assert.equal(timers.size, 0);

// Permission rejection, missing metadata, and late permission grants release streams.
const video = { ...target(), readyState: 0 };
page.video.value = video;
mediaRequest = () => Promise.reject(new DOMException('denied', 'NotAllowedError'));
await page.startCamera();
assert.match(page.error.value, /permission was denied/);
mediaRequest = () => Promise.reject(new DOMException('no device', 'NotFoundError'));
await page.startCamera();
assert.match(page.error.value, /No camera was found/);
let stopped = 0;
const stream = () => ({ getTracks: () => [{ stop: () => stopped++ }] });
mediaRequest = () => Promise.resolve(stream());
let camera = page.startCamera();
await flush();
timeout(15000);
await camera;
assert.equal(stopped, 1);
assert.equal(video.listeners.size, 0);
assert.equal(video.srcObject, null);
camera = page.startCamera();
await flush();
video.dispatch('loadedmetadata');
await camera;
assert.equal(page.cameraReady.value, true);
page.stopCamera();
assert.equal(stopped, 2);
let grant;
mediaRequest = () => new Promise(resolve => { grant = resolve; });
camera = page.startCamera();
timeout(15000);
await camera;
grant(stream());
await flush();
assert.equal(stopped, 3);
assert.equal(page.cameraReady.value, false);
assert.equal(timers.size, 0);

// Broken images reject immediately; pending images have bounded, cleaned listeners.
const image = { ...target(), complete: true, naturalWidth: 100, naturalHeight: 100 };
await assert.rejects(page.ensureImageReady({ complete: true, naturalWidth: 0, naturalHeight: 0 }), /could not load/);
image.complete = false;
const waiting = page.ensureImageReady(image);
timeout(15000);
await assert.rejects(waiting, /could not load/);
assert.equal(image.listeners.size, 0);
image.complete = true;
page.cropperRef.value = { getImageElement: () => image };
page.step.value = 'review';

// Every allocated matrix is released even if contour analysis or warping throws.
for (const failure of ['color', 'contour', 'approx', undefined]) {
    fault = failure;
    await page.runAutoDetect();
    assert.equal(page.detecting.value, false);
    assert.ok(resources.every(resource => resource.deleted === 1));
}
const corners = [{ x: 0, y: 0 }, { x: 100, y: 0 }, { x: 100, y: 100 }, { x: 0, y: 100 }];
for (const invalid of [[], Array(4).fill({ x: 0, y: 0 }), corners.map(p => ({ x: p.x + 1, y: p.y })), corners.map(p => ({ x: NaN, y: p.y }))]) {
    page.currentPoints.value = invalid;
    const count = resources.length;
    await page.processAndUpload();
    assert.match(page.error.value, /Invalid crop/);
    assert.equal(page.processing.value, false);
    assert.equal(resources.length, count);
}
page.currentPoints.value = corners;
fault = 'warp';
await page.processAndUpload();
assert.equal(page.processing.value, false);
assert.ok(resources.every(resource => resource.deleted === 1));
fault = undefined;

// Failed, cancelled and timed out HTTP requests retain the scan and allow retry.
for (const outcome of ['validation', 'network', 'http', 'cancel', 'timeout', 'rejected', 'accepted']) {
    const upload = page.processAndUpload();
    await flush();
    assert.equal(page.processing.value, true);
    assert.ok(resources.every(resource => resource.deleted === 1));
    if (outcome === 'validation') callbacks.onError({ file: 'Rejected' });
    if (outcome === 'network') assert.equal(callbacks.onNetworkError(), false);
    if (outcome === 'http') assert.equal(callbacks.onHttpException(), false);
    if (outcome === 'cancel') callbacks.onCancel();
    if (outcome === 'timeout') timeout(60000);
    if (outcome === 'rejected' || outcome === 'accepted') {
        callbacks.onSuccess({ props: { flash: { upload_results: [{ status: outcome === 'rejected' ? 'failed' : 'accepted', message: 'Rejected' }] } } });
    }
    callbacks.onFinish();
    await upload;
    assert.equal(page.processing.value, false);
    assert.equal(page.step.value, 'review');
    if (outcome !== 'accepted') assert.ok(page.error.value);
    assert.equal(timers.size, 0);
}
assert.equal(visits, 1);

// Leaving the scanner cancels pending work and stops late camera streams.
const pendingUpload = page.processAndUpload();
await flush();
mediaRequest = () => new Promise(resolve => { grant = resolve; });
camera = page.startCamera();
window.cv = null;
page.loadOpenCV();
unmounted.shift()();
await camera;
await pendingUpload;
grant(stream());
await flush();
assert.equal(page.processing.value, false);
assert.equal(stopped, 4);
assert.equal(timers.size, 0);

// Crop handles stay inside the image, and unmount removes all global drag handlers.
const events = [];
const cropper = setup('resources/js/Pages/Scanner/PerspectiveCropper.vue', 'Cropper', {}, (...args) => events.push(args));
cropper.imageRef.value = { naturalWidth: 100, naturalHeight: 100, getBoundingClientRect: () => ({ left: 25, top: 25, width: 50, height: 50 }) };
cropper.container.value = { getBoundingClientRect: () => ({ left: 0, top: 0, width: 100, height: 100 }) };
cropper.onImageLoad();
cropper.startDrag(0, {});
cropper.onDrag({ type: 'mousemove', clientX: -100, clientY: 200, preventDefault() {} });
assert.deepEqual(cropper.getRelativePoints()[0], { x: 0, y: 100 });
assert.equal(document.listeners.size, 5);
const count = events.length;
unmounted.shift()();
assert.equal(document.listeners.size, 0);
assert.equal(events.length, count);
cropper.onImageError();
assert.equal(cropper.getRelativePoints(), null);
assert.equal(events.at(-1)[0], 'error');
JS;

    $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});
