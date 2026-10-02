<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Jobs\TimestampFormatter;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

it('shares one validated preference contract independent of the user timezone column', function (): void {
    $user = User::factory()->create(['timezone' => 'Pacific/Honolulu']);
    UserPreference::create(['user_id' => $user->id, 'timezone' => 'Europe/Oslo', 'language' => 'nb', 'currency' => 'EUR', 'date_format' => 'd.m.Y']);
    $request = Request::create('/');
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => $user);
    $shared = app(HandleInertiaRequests::class)->share($request);
    expect($shared['auth']['user']['preferences'])->toBe([
        'language' => 'nb', 'date_format' => 'd.m.Y', 'currency' => 'EUR', 'timezone' => 'Europe/Oslo',
    ]);
    $user->preferences->update(['timezone' => 'Invalid/Zone', 'language' => 'invalid', 'currency' => 'bad', 'date_format' => 'bad']);
    expect($user->formattingPreferences())->toBe(['language' => 'en', 'date_format' => 'Y-m-d', 'currency' => 'NOK', 'timezone' => 'UTC']);
});

it('formats calendar dates original currencies and instant timestamps from persisted preferences', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
const prefs = { language: 'en', timezone: 'Pacific/Honolulu', date_format: 'Y-m-d', currency: 'NOK' };
const source = fs.readFileSync('resources/js/utils/datetime.ts', 'utf8').replace("import { usePage } from '@inertiajs/vue3';", '');
const compiled = stripTypeScriptTypes(source.replaceAll('export function ', 'function '));
const { formatPreferredDate, formatDateTime, formatCurrency } = new Function('usePage', compiled + '; return { formatPreferredDate, formatDateTime, formatCurrency };')(() => ({ props: { auth: { user: { preferences: prefs } } } }));
assert.equal(formatPreferredDate('2026-10-02'), '2026-10-02');
assert.equal(formatPreferredDate('2026-10-02T00:00:00Z'), '2026-10-02');
assert.match(formatPreferredDate('2026-10-02T00:00:00Z', true), /^2026-10-01/);
assert.equal(formatDateTime('2026-10-02', 'date'), '2026-10-02');
assert.match(formatCurrency(10, 'EUR'), /€10.00/);
assert.equal(formatCurrency(null), 'Conversion unavailable');
assert.equal(formatCurrency(10, 'Kr'), '10.00 Kr');
prefs.timezone = 'Europe/Oslo';
prefs.language = 'nb';
prefs.date_format = 'd.m.Y';
assert.match(formatDateTime('2026-10-02T00:00:00Z'), /^02.10.2026 02:00/);
assert.equal(formatCurrency(10, 'USD'), new Intl.NumberFormat('nb-NO', { style: 'currency', currency: 'USD' }).format(10));
prefs.date_format = 'd/m/Y';
assert.equal(formatPreferredDate('2026-10-02'), '02/10/2026');
prefs.date_format = 'm/d/Y';
assert.equal(formatPreferredDate('2026-10-02'), '10/02/2026');
JS;
    $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});

it('serializes job timestamps as explicit UTC instants including the Unix epoch', function (): void {
    config(['app.timezone' => 'Europe/Oslo']);
    expect(TimestampFormatter::format(0))->toBe('1970-01-01T00:00:00+00:00')
        ->and(TimestampFormatter::format('2026-10-02 12:00:00'))->toBe('2026-10-02T10:00:00+00:00')
        ->and(TimestampFormatter::format(null))->toBeNull();
});
