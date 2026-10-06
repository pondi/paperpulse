<?php

use App\Models\Document;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Voucher;
use Symfony\Component\Process\Process;

it('returns authoritative unread counts for repeated reads and deletions', function (): void {
    $user = User::factory()->create();
    $unread = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['type' => 'weekly_summary']]);
    $read = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => [], 'read_at' => now()]);
    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => []]);
    $this->actingAs($user);

    $this->postJson(route('notifications.read', $unread->id))->assertOk()->assertJsonPath('unread_count', 1);
    $this->postJson(route('notifications.read', $unread->id))->assertOk()->assertJsonPath('unread_count', 1);
    $this->deleteJson(route('notifications.destroy', $read->id))->assertOk()->assertJsonPath('unread_count', 1);
    $this->deleteJson(route('notifications.destroy', $unread->id))->assertOk()->assertJsonPath('unread_count', 1);
    $this->postJson(route('notifications.read-all'))->assertOk()->assertJsonPath('unread_count', 0);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonPath('unread_count', 0);
});

it('does not allow another user to read or delete a notification', function (): void {
    $owner = User::factory()->create();
    $notification = $owner->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => []]);
    $this->actingAs(User::factory()->create());
    $this->postJson(route('notifications.read', $notification->id))->assertNotFound();
    $this->deleteJson(route('notifications.destroy', $notification->id))->assertNotFound();
    expect($notification->fresh()->read_at)->toBeNull();
});

it('only includes destinations for currently accessible notification targets', function (string $model, string $type, string $field, string $route): void {
    $user = User::factory()->create();
    $owned = $model::factory()->create(['user_id' => $user->id]);
    $foreign = $model::factory()->create();
    $allowed = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['type' => $type, $field => $owned->id]]);
    $denied = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['type' => $type, $field => $foreign->id]]);

    $notifications = collect($this->actingAs($user)->getJson(route('notifications.index'))->assertOk()->json('notifications'))->keyBy('id');
    expect($notifications[$allowed->id]['url'])->toBe(route($route, $owned->id))
        ->and($notifications[$denied->id]['url'])->toBeNull();
})->with([
    [Receipt::class, 'receipt_processed', 'receipt_id', 'receipts.show'],
    [Receipt::class, 'receipt_shared', 'receipt_id', 'receipts.show'],
    [Document::class, 'document_shared', 'document_id', 'documents.show'],
    [Voucher::class, 'voucher_expiring', 'voucher_id', 'vouchers.show'],
    [File::class, 'warranty_ending', 'file_id', 'files.show'],
    [File::class, 'duplicate_file_detected', 'existing_file_id', 'files.show'],
]);

it('removes a shared destination after access is revoked', function (): void {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id, 'file_type' => 'receipt']);
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id]);
    $share = FileShare::create([
        'file_id' => $file->id, 'file_type' => 'receipt', 'shared_by_user_id' => $owner->id,
        'shared_with_user_id' => $recipient->id, 'permission' => 'view', 'shared_at' => now(),
    ]);
    $recipient->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['type' => 'receipt_shared', 'receipt_id' => $receipt->id]]);
    $this->actingAs($recipient)->getJson(route('notifications.index'))->assertOk()->assertJsonPath('notifications.0.url', route('receipts.show', $receipt->id));
    $share->delete();
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonPath('notifications.0.url', null);
});

it('keeps the unread count independent of the fifty notification display limit', function (): void {
    $user = User::factory()->create();
    for ($index = 0; $index < 53; $index++) {
        $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['type' => 'weekly_summary']]);
    }
    $this->actingAs($user)->getJson(route('notifications.index'))->assertOk()
        ->assertJsonCount(50, 'notifications')->assertJsonPath('unread_count', 53)
        ->assertJsonPath('notifications.0.url', route('dashboard'));
});

it('keeps the bell counts correct and polls through disconnects without leaking timers', function (): void {
    $process = new Process(['node', '--input-type=module'], base_path());
    $process->setInput(<<<'JS'
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';

const file = 'resources/js/Components/Features/NotificationBell.vue';
const { descriptor } = parse(fs.readFileSync(file, 'utf8'));
const script = compileScript(descriptor, { id: file });
assert.deepEqual(compileTemplate({ source: descriptor.template.content, filename: file, id: file, compilerOptions: { bindingMetadata: script.bindings } }).errors, []);
const source = descriptor.scriptSetup.content.replace(/^import[\s\S]*?from ['"][^'"]+['"];?/gm, '');
async function createBell(withEcho) {
  const timers = new Map();
  const listeners = new Map();
  const connectionListeners = new Map();
  let mounted, unmounted, timerId = 0, left = 0, subscriptionError;
  let records = [
    { id: 'a', read_at: null, data: { type: 'weekly_summary' } },
    { id: 'b', read_at: 'already read', data: {} },
    { id: 'c', read_at: null, data: {} },
  ];
  const unread = () => records.filter(n => !n.read_at).length;
  const connection = { state: 'connecting', bind: (event, callback) => connectionListeners.set(event, callback), unbind: event => connectionListeners.delete(event) };
  const channel = { notification: () => channel, error: callback => { subscriptionError = callback; return channel; } };
  const document = { hidden: false, addEventListener: (event, callback) => listeners.set(event, callback), removeEventListener: event => listeners.delete(event) };
  const context = {
    ref: value => ({ value }), onMounted: callback => { mounted = callback; }, onUnmounted: callback => { unmounted = callback; },
    usePage: () => ({ props: { auth: { user: { id: 7 } } } }),
    useDateFormatter: () => ({ formatDate: date => date, formatCurrency: (amount, currency) => `${amount} ${currency}` }),
    router: { visit: url => { context.visited = url; } },
    console, document, setInterval: callback => { timers.set(++timerId, callback); return timerId; }, clearInterval: id => timers.delete(id),
    window: { Echo: withEcho ? { connector: { pusher: { connection } }, private: () => channel, leave: () => { left++; } } : undefined },
    axios: {
      get: async () => ({ data: { notifications: structuredClone(records), unread_count: unread() } }),
      post: async url => { const id = url.split('/')[2]; const notification = records.find(n => n.id === id); if (notification) notification.read_at = 'read'; return { data: { unread_count: unread() } }; },
      delete: async url => { records = records.filter(n => n.id !== url.split('/')[2]); return { data: { unread_count: unread() } }; },
    },
  };
  vm.runInNewContext(source + ';globalThis.bell = { notifications, unreadCount, markAsRead, deleteNotification, getNotificationTitle, getNotificationMessage, handleNotificationClick };', context);
  mounted();
  await Promise.resolve(); await Promise.resolve();
  return { bell: context.bell, timers, listeners, connectionListeners, document, context, unmounted, subscriptionError,
    left: () => left,
    state: state => { connection.state = state; connectionListeners.get('state_change')({ current: state }); },
  };
}
const client = await createBell(true);
assert.equal(client.bell.unreadCount.value, 2);
await client.bell.markAsRead('a');
await client.bell.markAsRead('a');
assert.equal(client.bell.unreadCount.value, 1);
await client.bell.deleteNotification('b');
assert.equal(client.bell.unreadCount.value, 1);
await client.bell.deleteNotification('c');
assert.equal(client.bell.unreadCount.value, 0);
assert.equal(client.bell.notifications.value.length, 1);
assert.equal(client.timers.size, 1);
client.state('connected'); assert.equal(client.timers.size, 0);
client.state('disconnected'); assert.equal(client.timers.size, 1);
client.state('unavailable'); assert.equal(client.timers.size, 1);
client.document.hidden = true; client.listeners.get('visibilitychange')(); assert.equal(client.timers.size, 0);
client.document.hidden = false; client.listeners.get('visibilitychange')(); assert.equal(client.timers.size, 1);
client.state('connected'); assert.equal(client.timers.size, 0);
client.subscriptionError(); assert.equal(client.timers.size, 1);
for (const data of [
  { type: 'voucher_expiring', merchant_name: 'Shop', voucher_code: 'SAVE', expiry_date: '2026-11-01', days_remaining: 30 },
  { type: 'warranty_ending', product_name: 'Device', warranty_end_date: '2026-11-01', days_remaining: 30 },
  { type: 'receipt_shared', receipt_title: 'Purchase', shared_by_name: 'Owner' },
  { type: 'document_shared', document_title: 'Agreement', shared_by_name: 'Owner' },
  { type: 'weekly_summary', week_start: '2026-10-01', week_end: '2026-10-07', total_receipts: 3, total_amount: 40, currency: 'NOK' },
]) {
  assert.notEqual(client.bell.getNotificationTitle({ data }), 'notification');
  assert.ok(client.bell.getNotificationMessage({ data }).length > 0);
}
client.bell.handleNotificationClick({ read_at: 'read', data: {}, url: '/files/42' });
assert.equal(client.context.visited, '/files/42');
client.unmounted();
assert.equal(client.timers.size, 0); assert.equal(client.listeners.size, 0); assert.equal(client.connectionListeners.size, 0); assert.equal(client.left(), 1);
const pollingOnly = await createBell(false);
assert.equal(pollingOnly.timers.size, 1);
pollingOnly.listeners.get('visibilitychange')(); assert.equal(pollingOnly.timers.size, 1);
pollingOnly.unmounted(); assert.equal(pollingOnly.timers.size, 0);
JS);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});
