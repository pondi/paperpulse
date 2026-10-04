<?php

use App\Http\Resources\Inertia\JobHistoryInertiaResource;
use App\Models\FileShare;
use App\Models\JobHistory;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\ReceiptSharedNotification;

it('serializes completed job durations as whole seconds', function (): void {
    $job = new JobHistory([
        'started_at' => now()->startOfSecond(),
        'finished_at' => now()->startOfSecond()->addMilliseconds(1500),
    ]);
    $resource = JobHistoryInertiaResource::asChild($job);

    expect($resource->toArray(request())['duration'])->toBe(1);
});

it('keeps unfinished job duration unknown', function (): void {
    $resource = JobHistoryInertiaResource::asChild(new JobHistory(['started_at' => now()]));

    expect($resource->toArray(request())['duration'])->toBeNull();
});

it('formats cast decimal receipt amounts in share notifications', function (): void {
    $receipt = new Receipt(['total_amount' => '123.45', 'currency' => 'NOK']);
    $receipt->id = 7;
    $receipt->setRelation('merchant', null);
    $share = new FileShare(['permission' => 'view']);
    $notification = new ReceiptSharedNotification($receipt, new User(['name' => 'Owner']), $share);

    expect($notification->toMail(new User(['name' => 'Recipient']))->introLines)->toContain('Amount: 123.45 NOK');
});
