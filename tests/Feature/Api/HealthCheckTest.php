<?php

use App\Services\ReadinessCheck;

beforeEach(function (): void {
    $this->mock(ReadinessCheck::class)->shouldReceive('check')->once()->andReturn([
        'status' => 'ok',
        'components' => [
            'database' => ['status' => 'ok', 'latency_ms' => 0],
            'redis' => ['status' => 'ok'],
            'queue' => ['status' => 'ok'],
        ],
    ]);
});

describe('health check', function () {
    it('returns ok status with component checks', function () {
        $response = $this->getJson('/api/health');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'version',
            'components' => [
                'database' => ['status'],
                'redis' => ['status'],
                'queue' => ['status'],
            ],
        ]);
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('components.database.status', 'ok');
    });

    it('does not require authentication', function () {
        $response = $this->getJson('/api/health');

        $response->assertSuccessful();
    });

    it('includes latency_ms for database check', function () {
        $response = $this->getJson('/api/health');

        $response->assertSuccessful();
        expect($response->json('components.database.latency_ms'))->toBeGreaterThanOrEqual(0);
    });
});
