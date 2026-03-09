<?php

use App\Models\JobHistory;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when job has null file_id', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $job = JobHistory::create([
        'uuid' => fake()->uuid(),
        'name' => 'TestJob',
        'queue' => 'default',
        'status' => 'completed',
        'file_id' => null,
    ]);

    $response = $this->getJson("/api/v1/jobs/{$job->uuid}");

    $response->assertNotFound();
});

it('returns 404 when job file belongs to another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    $file = \App\Models\File::factory()->create(['user_id' => $otherUser->id]);

    $job = JobHistory::create([
        'uuid' => fake()->uuid(),
        'name' => 'TestJob',
        'queue' => 'default',
        'status' => 'completed',
        'file_id' => $file->id,
    ]);

    $response = $this->getJson("/api/v1/jobs/{$job->uuid}");

    $response->assertNotFound();
});
