<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Symfony\Component\Uid\Uuid;

it('generates a correlation id when the client sends none', function (): void {
    $response = $this->getJson('/up');

    $correlationId = $response->headers->get('X-Correlation-ID');

    expect($correlationId)->not->toBeNull();
    expect(Uuid::isValid($correlationId))->toBeTrue();
});

it('preserves the correlation id sent by the client', function (): void {
    $clientId = (string) Uuid::v4();

    $response = $this->getJson('/up', ['X-Correlation-ID' => $clientId]);

    expect($response->headers->get('X-Correlation-ID'))->toBe($clientId);
});

it('always returns the correlation id header, even on error responses', function (): void {
    $response = $this->getJson('/api/v1/this-route-does-not-exist');

    $response->assertStatus(404);

    $correlationId = $response->headers->get('X-Correlation-ID');

    expect($correlationId)->not->toBeNull();
    expect(Uuid::isValid($correlationId))->toBeTrue();
});

it('shares the correlation id with the log context', function (): void {
    $clientId = (string) Uuid::v4();

    $this->getJson('/up', ['X-Correlation-ID' => $clientId]);

    expect(Log::sharedContext())->toHaveKey('correlation_id', $clientId);
});
