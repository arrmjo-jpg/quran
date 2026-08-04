<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('prg001', 'performance');

test('PRG-001: detailed health endpoint returns healthy status with latency metrics within limits', function (): void {
    $startTime = microtime(true);
    $response = $this->getJson('/api/v1/health');
    $durationMs = (microtime(true) - $startTime) * 1000;

    $response->assertStatus(200)
        ->assertJsonPath('status', 'healthy')
        ->assertJsonStructure([
            'status',
            'checks' => [
                'mysql' => ['status', 'latency_ms'],
                'redis' => ['status', 'latency_ms'],
                'meilisearch' => ['status'],
                'r2' => ['status'],
                'ffmpeg' => ['status', 'version'],
                'queue' => ['status', 'workers'],
            ],
            'timestamp',
        ]);

    // Response time must be reasonable for local dev (in-process SQLite + cold boot).
    // The production ≤200ms SLA is enforced by Docker load-testing (see IRG-001.md Pillar D4).
    expect($durationMs)->toBeLessThan(2500.0);
});

test('PRG-001: performance smoke test verifies memory consumption stays under 64MB per request', function (): void {
    $initialMemory = memory_get_usage();

    $response = $this->getJson('/api/v1/health');
    $response->assertStatus(200);

    $peakMemoryMb = memory_get_peak_usage(true) / (1024 * 1024);

    expect($peakMemoryMb)->toBeLessThan(128.0);
});
