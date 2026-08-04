<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Infrastructure\Database\Models\AuditLogModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class);

function audit_user(string $email): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Uuid::v4(),
        'email' => $email,
        'name' => 'Audit Test User',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

it('records the correlation id on the audit log entry', function (): void {
    $correlationId = (string) Uuid::v4();

    $this->getJson('/api/v1/this-route-does-not-exist', ['X-Correlation-ID' => $correlationId]);

    $entry = AuditLogModel::query()->latest('created_at')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->correlation_id)->toBe($correlationId);
});

it('records a positive execution duration', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@test.test', 'password' => 'wrong']);

    $entry = AuditLogModel::query()->latest('created_at')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->execution_duration_ms)->toBeGreaterThan(0);
});

it('leaves actor_id null for a guest and populates it once authenticated', function (): void {
    $this->getJson('/api/v1/me');

    $guestEntry = AuditLogModel::query()->latest('created_at')->first();

    expect($guestEntry->actor_id)->toBeNull();

    $user = audit_user('audit-actor@test.test');
    $token = $user->createToken('test')->plainTextToken;

    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"]);

    $authedEntry = AuditLogModel::query()->latest('created_at')->first();

    expect($authedEntry->actor_id)->toBe($user->id);
});

it('records the real response status', function (): void {
    $this->getJson('/api/v1/this-route-does-not-exist');
    expect(AuditLogModel::query()->latest('created_at')->first()->response_status)->toBe(404);

    $this->getJson('/api/v1/languages');
    expect(AuditLogModel::query()->latest('created_at')->first()->response_status)->toBe(200);
});

it('captures ip and user agent from the real request', function (): void {
    $this->withHeaders(['User-Agent' => 'AuditTestAgent/1.0'])
        ->getJson('/api/v1/languages');

    $entry = AuditLogModel::query()->latest('created_at')->first();

    expect($entry->user_agent)->toBe('AuditTestAgent/1.0')
        ->and($entry->ip)->not->toBeNull();
});

it('never logs sensitive values such as passwords, tokens, OTP codes or recovery codes', function (): void {
    $secretPassword = 'S3cretPassword!Distinctive';

    $this->postJson('/api/v1/auth/login', [
        'email' => 'audit-secret@test.test',
        'password' => $secretPassword,
    ]);

    $entry = AuditLogModel::query()->latest('created_at')->first();

    $serialized = json_encode($entry->toArray());

    expect($serialized)->not->toContain($secretPassword)
        ->and($entry->getAttributes())->not->toHaveKey('password')
        ->and($entry->getAttributes())->not->toHaveKey('password_hash')
        ->and($entry->getAttributes())->not->toHaveKey('token')
        ->and($entry->getAttributes())->not->toHaveKey('otp')
        ->and($entry->getAttributes())->not->toHaveKey('recovery_codes');
});

it('does not fail the real response when the audit write itself fails', function (): void {
    Log::spy();

    Schema::drop('audit_logs');

    $response = $this->getJson('/api/v1/languages');

    $response->assertStatus(200);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Audit log write failed.'
            && array_key_exists('exception', $context)
        );
});
