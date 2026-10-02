<?php

use App\Models\File;
use App\Models\OrganizationRun;
use App\Services\OrganizationRevisionService;
use App\Services\OrganizationRunService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

it('enforces one unresolved run and settles zero recommendation inputs', function () {
    $file = File::factory()->create();
    $service = app(OrganizationRunService::class);
    $run = $service->start($file->user_id);
    expect($service->start($file->user_id)->id)->toBe($run->id);
    $running = $service->begin($file->user_id, $run->id);
    expect($running->status)->toBe('running')->and($service->begin($file->user_id, $run->id))->toBeNull();
    $service->finish($running);
    expect($run->fresh()->status)->toBe('completed')->and($service->start($file->user_id))->toBeNull();
});

it('pending and conflicting decisions block new runs while late changes survive settlement', function () {
    $file = File::factory()->create();
    $service = app(OrganizationRunService::class);
    $run = $service->start($file->user_id);
    $recommendation = $run->recommendations()->create(['user_id' => $file->user_id,
        'operation' => ['type' => 'rename'], 'before_state' => [], 'signature' => str_repeat('a', 64), 'confidence' => .9, 'reason' => 'Clearer name']);
    $service->finish($run);
    expect($run->fresh()->status)->toBe('awaiting_decisions');
    $file->update(['organization_summary' => ['title' => 'Late change']]);
    expect($service->start($file->user_id)->id)->toBe($run->id);
    $recommendation->update(['status' => 'conflict']);
    $service->finish($run);
    expect($run->fresh()->status)->toBe('awaiting_decisions');
    $recommendation->update(['status' => 'declined']);
    $service->finish($run);
    expect(app(OrganizationRevisionService::class)->hasChanges($file->user_id))->toBeTrue();
    expect($service->start($file->user_id)->id)->not->toBe($run->id);
});

it('debounces scheduled runs and skips equivalent inputs', function () {
    $file = File::factory()->create();
    $service = app(OrganizationRunService::class);
    expect($service->start($file->user_id, true))->toBeNull();
    $run = $service->start($file->user_id);
    $service->finish($run);
    $file->update(['organization_summary' => ['title' => 'Temporary']]);
    $file->update(['organization_summary' => null]);
    expect($service->start($file->user_id))->toBeNull()->and(OrganizationRun::query()->count())->toBe(1);
});

it('retries failures on the same run with bounded attempts and rejects another owner', function () {
    $file = File::factory()->create();
    $other = File::factory()->create();
    $service = app(OrganizationRunService::class);
    $run = $service->start($file->user_id);
    expect(fn () => $service->begin($other->user_id, $run->id))->toThrow(ModelNotFoundException::class);
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $running = $service->begin($file->user_id, $run->id);
        $service->fail($running);
        expect($service->start($file->user_id)->id)->toBe($run->id);
        if ($attempt < 3) {
            expect($service->retry($file->user_id, $run->id)->id)->toBe($run->id);
        }
    }
    expect(fn () => $service->retry($file->user_id, $run->id))->toThrow(ValidationException::class);
});
