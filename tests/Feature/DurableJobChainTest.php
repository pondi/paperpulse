<?php

use App\Models\JobHistory;
use App\Services\Files\FileJobChainDispatcher;
use App\Services\Jobs\JobChainPlan;
use App\Services\Jobs\JobMetadataPersistence;
use App\Services\Jobs\JobParentStatusCalculator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Feature\DurableLifecycleTestJob;

it('uses one logical step identity in serialized queue payload and retries', function (): void {
    $job = new DurableLifecycleTestJob((string) Str::uuid());
    $beforeDispatch = $job->getUUID();
    Queue::connection('database')->push($job, '', 'documents');
    $payload = json_decode(DB::table('jobs')->first()->payload, true);
    $retry = unserialize($payload['data']['command']);

    expect($payload['uuid'])->toBe($beforeDispatch)
        ->and($retry->getUUID())->toBe($beforeDispatch)
        ->and($payload['chain_id'])->toBe($job->getJobID());
});

it('persists every planned step before the first job is dispatched', function (): void {
    Bus::fake();
    config(['ai.file_processing_provider' => 'textract+openai']);
    $id = (string) Str::uuid();
    Cache::put("job.{$id}.fileMetaData", ['fileExtension' => 'pdf', 'jobName' => 'Test pipeline', 'metadata' => []]);
    (new FileJobChainDispatcher)->dispatch($id, 'document');
    $parent = JobHistory::where('uuid', $id)->first();

    expect($parent->metadata['plannedSteps'])->toHaveCount(3)
        ->and($parent->tasks()->where('status', 'pending')->count())->toBe(3);
    $first = $parent->tasks()->orderBy('order_in_chain')->first();
    $first->update(['status' => 'completed', 'progress' => 100]);
    JobParentStatusCalculator::update($id);
    expect($parent->fresh()->status)->toBe('processing')
        ->and($parent->fresh()->progress)->toBe(33)
        ->and($parent->fresh()->finished_at)->toBeNull();
});

it('a retry keeps one history row and a completed redelivery does not repeat work', function (): void {
    $id = (string) Str::uuid();
    $job = new DurableLifecycleTestJob($id);
    JobMetadataPersistence::store($id, ['jobName' => 'Retry pipeline']);
    $job->shouldThrow = true;
    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
    expect(JobHistory::where('uuid', $job->uuid)->first()->status)->toBe('retrying');
    $job->shouldThrow = false;
    $job->handle();
    $job->handle();
    expect(JobHistory::where('uuid', $job->uuid)->count())->toBe(1)
        ->and($job->executions)->toBe(2)
        ->and(JobHistory::where('uuid', $id)->first()->status)->toBe('completed');
});

it('terminal failure cancels pending required steps before completing the parent', function (): void {
    $id = (string) Str::uuid();
    $first = new DurableLifecycleTestJob($id);
    $second = new DurableLifecycleTestJob($id);
    $metadata = ['jobName' => 'Failing pipeline', 'plannedSteps' => [
        ['uuid' => $first->uuid, 'class' => $first::class, 'order' => 1],
        ['uuid' => $second->uuid, 'class' => $second::class, 'order' => 2],
    ]];
    JobHistory::create(['uuid' => $id, 'name' => 'Parent', 'queue' => 'default', 'status' => 'pending', 'metadata' => $metadata]);
    JobMetadataPersistence::store($id, $metadata);
    JobHistory::create(['uuid' => $second->uuid, 'parent_uuid' => $id, 'name' => 'Later', 'queue' => 'default', 'status' => 'pending']);
    $first->failed(new RuntimeException('Exhausted attempts'));

    expect(JobHistory::where('uuid', $id)->first()->status)->toBe('failed')
        ->and(JobHistory::where('uuid', $second->uuid)->first()->status)->toBe('cancelled')
        ->and(JobHistory::where('uuid', $first->uuid)->count())->toBe(1);
});

it('a missing planned task cannot make its parent look completed', function (): void {
    $parent = JobHistory::create(['uuid' => (string) Str::uuid(), 'name' => 'Incomplete plan', 'queue' => 'default', 'status' => 'processing', 'metadata' => ['plannedSteps' => [['uuid' => (string) Str::uuid(), 'order' => 1]]]]);
    expect(JobParentStatusCalculator::calculate($parent))->toBe('pending');
});

it('a dynamically prepended conversion is persisted once and keeps the parent active', function (): void {
    Bus::fake();
    $id = (string) Str::uuid();
    Cache::put("job.{$id}.fileMetaData", ['fileExtension' => 'docx', 'jobName' => 'Office pipeline', 'metadata' => []]);
    (new FileJobChainDispatcher)->dispatch($id, 'document');
    $parent = JobHistory::where('uuid', $id)->first();
    $first = $parent->tasks()->orderBy('order_in_chain')->first();
    $conversion = new DurableLifecycleTestJob($id);
    JobChainPlan::prependStep($id, $first->uuid, $conversion);
    JobChainPlan::prependStep($id, $first->uuid, $conversion);

    $plan = $parent->fresh()->metadata['plannedSteps'];
    expect($plan[1]['uuid'])->toBe($conversion->uuid)
        ->and($parent->tasks()->where('uuid', $conversion->uuid)->count())->toBe(1)
        ->and($parent->tasks()->where('uuid', $conversion->uuid)->first()->order_in_chain)->toBe(2);
    $first->update(['status' => 'completed', 'progress' => 100]);
    JobParentStatusCalculator::update($id);
    expect($parent->fresh()->status)->toBe('processing');
});
