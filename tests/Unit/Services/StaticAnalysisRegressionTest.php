<?php

use App\Http\Resources\Inertia\JobHistoryInertiaResource;
use App\Models\File;
use App\Models\FileShare;
use App\Models\JobHistory;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\ReceiptSharedNotification;
use Illuminate\Database\Eloquent\Collection;

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

it('reads receipt source data from current and legacy JSON encodings', function (mixed $raw, array $expected): void {
    $receipt = new Receipt;
    $receipt->setRawAttributes(['receipt_data' => $raw]);

    expect($receipt->sourceData())->toBe($expected);
})->with([
    'empty' => [null, []],
    'array' => ['{"totals":{"total_amount":42}}', ['totals' => ['total_amount' => 42]]],
    'legacy string' => [json_encode('{"totals":{"total_amount":42}}'), ['totals' => ['total_amount' => 42]]],
]);

it('uses parent job status and progress when processing has no child tasks', function (): void {
    $job = new JobHistory(['status' => 'pending', 'progress' => 0]);
    $job->setRelation('tasks', new Collection);
    $file = new File(['status' => 'pending']);
    $file->setRelation('processingJobs', new Collection([$job]));

    expect($file->processingSummary())->toMatchArray([
        'state' => 'pending', 'stage' => null, 'attempt' => null, 'progress' => 0,
    ]);
});
