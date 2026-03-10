<?php

declare(strict_types=1);

use App\Models\File;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\BulkOperationCompleted;
use App\Notifications\DuplicateFileDetected;
use App\Notifications\ReceiptProcessed;
use App\Notifications\ScannerFilesImported;

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
