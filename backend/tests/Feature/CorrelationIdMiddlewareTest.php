<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
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

it('propagates the correlation id to every log channel, not just the default one', function (): void {
    $clientId = (string) Uuid::v4();

    $this->getJson('/up', ['X-Correlation-ID' => $clientId]);

    foreach (['single', 'daily', 'stderr'] as $channel) {
        $handler = new TestHandler;
        Log::channel($channel)->getLogger()->pushHandler($handler);

        Log::channel($channel)->info("probe-{$channel}");

        $records = $handler->getRecords();

        expect($records)->toHaveCount(1);
        expect($records[0]->context)->toHaveKey('correlation_id', $clientId)
            ->and($records[0]->message)->toBe("probe-{$channel}");
    }
});

it('ignores a malformed correlation id and generates a fresh valid one', function (): void {
    $response = $this->getJson('/up', ['X-Correlation-ID' => 'not-a-uuid-at-all']);

    $returned = $response->headers->get('X-Correlation-ID');

    expect($returned)->not->toBeNull()
        ->and($returned)->not->toBe('not-a-uuid-at-all')
        ->and(Uuid::isValid($returned))->toBeTrue();
});

it('never reflects a CRLF header-injection attempt', function (): void {
    $malicious = "abc\r\nX-Injected-Header: evil";

    $response = $this->getJson('/up', ['X-Correlation-ID' => $malicious]);

    $returned = $response->headers->get('X-Correlation-ID');

    expect($returned)->not->toContain("\r")
        ->and($returned)->not->toContain("\n")
        ->and($returned)->not->toBe($malicious)
        ->and(Uuid::isValid($returned))->toBeTrue();

    $response->assertHeaderMissing('X-Injected-Header');
});

it('never reflects a path-traversal-like correlation id', function (): void {
    $malicious = '../../etc/passwd';

    $response = $this->getJson('/up', ['X-Correlation-ID' => $malicious]);

    $returned = $response->headers->get('X-Correlation-ID');

    expect($returned)->not->toBe($malicious)
        ->and(Uuid::isValid($returned))->toBeTrue();
});

it('attaches the correlation id to a validation exception (422) response', function (): void {
    $clientId = (string) Uuid::v4();

    $response = $this->postJson('/api/v1/auth/login', [], ['X-Correlation-ID' => $clientId]);

    $response->assertStatus(422);
    expect($response->headers->get('X-Correlation-ID'))->toBe($clientId);
});

it('attaches the correlation id to an authentication exception response', function (): void {
    $clientId = (string) Uuid::v4();

    $response = $this->getJson('/api/v1/me', ['X-Correlation-ID' => $clientId]);

    expect($response->headers->get('X-Correlation-ID'))->toBe($clientId);
});

it('attaches the correlation id to an authorization exception (403) response', function (): void {
    Route::middleware('web')->get('/__test-authz-probe', function (): void {
        throw new AuthorizationException('Not allowed for this test.');
    });

    $clientId = (string) Uuid::v4();

    $response = $this->getJson('/__test-authz-probe', ['X-Correlation-ID' => $clientId]);

    $response->assertStatus(403);
    expect($response->headers->get('X-Correlation-ID'))->toBe($clientId);
});
