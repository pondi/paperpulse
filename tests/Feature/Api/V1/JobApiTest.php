<?php

use App\Models\File;
use App\Models\JobHistory;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('GET /jobs/{job_id}', function () {
    it('returns job status with tasks', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);
        $parentUuid = (string) Str::uuid();

        $parent = JobHistory::create([
            'uuid' => $parentUuid,
            'name' => 'process-file',
            'queue' => 'default',
            'status' => 'processing',
            'progress' => 50,
            'file_id' => $file->id,
            'started_at' => now()->subMinutes(2),
        ]);

        JobHistory::create([
            'uuid' => (string) Str::uuid(),
            'parent_uuid' => $parentUuid,
            'name' => 'Upload',
            'queue' => 'default',
            'status' => 'completed',
            'order_in_chain' => 1,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(1),
        ]);

        JobHistory::create([
            'uuid' => (string) Str::uuid(),
            'parent_uuid' => $parentUuid,
            'name' => 'OCR Extraction',
            'queue' => 'default',
            'status' => 'processing',
            'order_in_chain' => 2,
            'started_at' => now()->subMinutes(1),
        ]);

        $response = $this->getJson(route('api.jobs.show', ['jobId' => $parentUuid]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.id', $parentUuid);
        $response->assertJsonPath('data.status', 'processing');
        $response->assertJsonPath('data.progress', 50);
        $response->assertJsonPath('data.file_id', $file->id);
        $response->assertJsonPath('data.current_step', 'OCR Extraction');
        $response->assertJsonCount(2, 'data.tasks');
    });

    it('returns completed job', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);
        $parentUuid = (string) Str::uuid();

        JobHistory::create([
            'uuid' => $parentUuid,
            'name' => 'process-file',
            'queue' => 'default',
            'status' => 'completed',
            'progress' => 100,
            'file_id' => $file->id,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);

        $response = $this->getJson(route('api.jobs.show', ['jobId' => $parentUuid]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.progress', 100);
        expect($response->json('data.completed_at'))->not->toBeNull();
    });

    it('returns failed job with error', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);
        $parentUuid = (string) Str::uuid();

        JobHistory::create([
            'uuid' => $parentUuid,
            'name' => 'process-file',
            'queue' => 'default',
            'status' => 'failed',
            'progress' => 30,
            'file_id' => $file->id,
            'exception' => 'OCR extraction failed',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);

        $response = $this->getJson(route('api.jobs.show', ['jobId' => $parentUuid]));

        $response->assertSuccessful();
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('data.error', 'OCR extraction failed');
    });

    it('returns 404 for non-existent job', function () {
        $response = $this->getJson(route('api.jobs.show', ['jobId' => (string) Str::uuid()]));

        $response->assertNotFound();
    });

    it('returns 404 for another users job', function () {
        $other = User::factory()->create();
        $file = File::factory()->create(['user_id' => $other->id]);
        $parentUuid = (string) Str::uuid();

        JobHistory::create([
            'uuid' => $parentUuid,
            'name' => 'process-file',
            'queue' => 'default',
            'status' => 'completed',
            'file_id' => $file->id,
        ]);

        $response = $this->getJson(route('api.jobs.show', ['jobId' => $parentUuid]));

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(route('api.jobs.show', ['jobId' => (string) Str::uuid()]));

        $response->assertUnauthorized();
    });
});
