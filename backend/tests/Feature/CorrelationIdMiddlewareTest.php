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

it('returns a consistent JSON envelope with the correlation id on an unknown route (404)', function (): void {
    $clientId = (string) Uuid::v4();

    $response = $this->getJson('/api/v1/this-route-does-not-exist', ['X-Correlation-ID' => $clientId]);

    $response->assertStatus(404)
        ->assertHeader('X-Correlation-ID', $clientId)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'correlation_id' => $clientId,
            ],
        ]);
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

it('returns a consistent JSON envelope with the correlation id on a validation exception (422)', function (): void {
    $clientId = (string) Uuid::v4();

    $response = $this->postJson('/api/v1/auth/login', [], ['X-Correlation-ID' => $clientId]);

    $response->assertStatus(422)
        ->assertHeader('X-Correlation-ID', $clientId)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'correlation_id' => $clientId,
            ],
        ]);
});

it('returns a consistent JSON envelope with the correlation id when a guest hits a protected route (401), with no redirect', function (): void {
    $clientId = (string) Uuid::v4();

    // Deliberately omit an explicit Accept header — this is exactly the
    // request shape that used to crash with a 500 (RouteNotFoundException
    // from the auth middleware's default redirect-to-login behaviour).
    $response = $this->call('GET', '/api/v1/me', server: [
        'HTTP_X_CORRELATION_ID' => $clientId,
    ]);

    $response->assertStatus(401)
        ->assertHeader('X-Correlation-ID', $clientId)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHENTICATED',
                'correlation_id' => $clientId,
            ],
        ]);
});

it('returns a consistent JSON envelope with the correlation id when a user lacks permission (403)', function (): void {
    Route::middleware('api')->get('/api/v1/__test-authz-probe', function (): void {
        throw new AuthorizationException('Not allowed for this test.');
    });

    $clientId = (string) Uuid::v4();

    $response = $this->getJson('/api/v1/__test-authz-probe', ['X-Correlation-ID' => $clientId]);

    $response->assertStatus(403)
        ->assertHeader('X-Correlation-ID', $clientId)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'correlation_id' => $clientId,
            ],
        ]);
});
