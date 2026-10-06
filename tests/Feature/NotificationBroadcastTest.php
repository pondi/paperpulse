<?php

declare(strict_types=1);

use App\Models\File;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\BulkOperationCompleted;
use App\Notifications\DuplicateFileDetected;
use App\Notifications\ReceiptProcessed;
use App\Notifications\ScannerFilesImported;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Reverb\Application;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Contracts\WebSocketConnection;
use Laravel\Reverb\Protocols\Pusher\Channels\PrivateChannel;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;

it('distinguishes successful HTTP authorization from Reverb private channel subscription', function (string $key, bool $subscribes): void {
    $user = User::factory()->create();
    $channelName = 'private-App.Models.User.'.$user->id;
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => $key,
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge('reverb');
    require base_path('routes/channels.php');

    $connection = new Connection(
        Mockery::mock(WebSocketConnection::class),
        new Application('test-app', $key, 'test-secret', 60, 30, ['*'], 10000),
        null,
    );
    $auth = $this->actingAs($user)->postJson('/broadcasting/auth', [
        'socket_id' => $connection->id(), 'channel_name' => $channelName,
    ])->assertOk()->json('auth');
    expect($auth)->toStartWith($key.':');

    $manager = $this->mock(ChannelConnectionManager::class);
    $manager->shouldReceive('for')->with($channelName)->once()->andReturnSelf();
    $channel = new PrivateChannel($channelName);

    if ($subscribes) {
        $manager->shouldReceive('add')->with($connection, [])->once();
        $channel->subscribe($connection, $auth);
    } else {
        $manager->shouldNotReceive('add');
        expect(fn () => $channel->subscribe($connection, $auth))->toThrow(ConnectionUnauthorized::class);
    }
})->with([
    ['paperpulse-public-key', true], ['base64:paperpulse-public-key', false],
]);

it('includes broadcast channel in receipt processed notification', function () {
    $user = User::factory()->create();
    $receipt = Receipt::factory()->create(['user_id' => $user->id]);

    $notification = new ReceiptProcessed($receipt);

    $channels = $notification->via($user);

    expect($channels)->toContain('broadcast');
    expect($channels)->toContain('database');
});

it('includes broadcast channel in scanner files imported notification', function () {
    $user = User::factory()->create();

    $notification = new ScannerFilesImported(5, 5, 0);

    $channels = $notification->via($user);

    expect($channels)->toContain('broadcast');
    expect($channels)->toContain('database');
});

it('includes broadcast channel in bulk operation completed notification', function () {
    $user = User::factory()->create();

    $notification = new BulkOperationCompleted('delete', 10);

    $channels = $notification->via($user);

    expect($channels)->toContain('broadcast');
    expect($channels)->toContain('database');
});

it('includes broadcast channel in duplicate file detected notification', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);

    $notification = new DuplicateFileDetected('test.pdf', $file, 'abc123');

    $channels = $notification->via($user);

    expect($channels)->toContain('broadcast');
    expect($channels)->toContain('database');
});

it('defines the user notification channel in channels.php', function () {
    $channelsFile = base_path('routes/channels.php');

    expect(file_exists($channelsFile))->toBeTrue();

    $content = file_get_contents($channelsFile);

    expect($content)->toContain('App.Models.User.{id}');
});

it('can fetch notifications via the existing endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/notifications')
        ->assertSuccessful()
        ->assertJsonStructure([
            'notifications',
            'unread_count',
        ]);
});

it('broadcasts notification data matching database data', function () {
    $user = User::factory()->create();
    $receipt = Receipt::factory()->create(['user_id' => $user->id]);

    $notification = new ReceiptProcessed($receipt, true);

    $data = $notification->toArray($user);

    expect($data)
        ->toHaveKey('type')
        ->toHaveKey('receipt_id')
        ->and($data['type'])->toBe('receipt_processed');
});
