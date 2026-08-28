<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use Modules\Core\Infrastructure\Database\Models\TrustedDeviceModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Security\TotpService;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class)->group('core', 'feature', 'security');

/*
|--------------------------------------------------------------------------
| Trusted devices as a credential — ADR-018 D1, D5, D6
|--------------------------------------------------------------------------
|
| The golden master records that trust used to affect nothing. This file
| covers what it does now, and — more importantly — what it must refuse.
|
| D5 deliberately weakens MFA for 30 days on one browser, so the cases that
| matter most are the negative ones: another account's token, an expired
| grant, a revoked device. A test suite that only proved the happy path here
| would be proving that the weakening works, and nothing about its limits.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function secUser(string $email, bool $mfa = true): UserModel
{
    $user = UserModel::query()->create([
        'id' => (string) Uuid::v7(),
        'email' => $email,
        'name' => 'Security User',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
        'mfa_enabled' => $mfa,
        'mfa_secret' => $mfa ? Crypt::encryptString(app(TotpService::class)->generateSecret()) : null,
    ]);

    return withSuperAdmin($user->fresh());
}

function trustTokenFor(UserModel $user, string $deviceId = 'browser-1'): string
{
    return test()->actingAs($user)
        ->withHeaders(['X-Device-ID' => $deviceId, 'User-Agent' => 'Mozilla/5.0'])
        ->postJson('/api/v1/admin/auth/devices/trust')
        ->assertOk()
        ->json('data.trust_token');
}

function loginWith(?string $trustToken): TestResponse
{
    $headers = $trustToken === null ? [] : ['X-Device-Trust-Token' => $trustToken];

    return test()->withHeaders($headers)->postJson('/api/v1/admin/auth/login', [
        'email' => 'trusted@quran.test',
        'password' => 'Pass123!',
    ]);
}

test('a trusted device skips the MFA challenge', function (): void {
    $user = secUser('trusted@quran.test');
    $token = trustTokenFor($user);

    $response = loginWith($token);

    $response->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
    expect($response->json('data'))->not->toHaveKey('mfa_required');
});

test('the same account without the token still gets a challenge', function (): void {
    $user = secUser('trusted@quran.test');
    trustTokenFor($user);

    loginWith(null)->assertOk()->assertJsonPath('data.mfa_required', true);
});

test('a garbage token gets a challenge, not an error', function (): void {
    secUser('trusted@quran.test');

    loginWith('NOT-A-REAL-TOKEN')->assertOk()->assertJsonPath('data.mfa_required', true);
});

test('another account\'s trust token does not work', function (): void {
    secUser('trusted@quran.test');
    $other = secUser('other@quran.test');

    // A perfectly valid token — for the wrong account. Verification is scoped
    // to the user logging in, so possession alone proves nothing.
    $othersToken = trustTokenFor($other, 'other-browser');

    loginWith($othersToken)->assertOk()->assertJsonPath('data.mfa_required', true);
});

test('an expired grant gets a challenge', function (): void {
    $user = secUser('trusted@quran.test');
    $token = trustTokenFor($user);

    TrustedDeviceModel::query()->where('user_id', $user->id)
        ->update(['expires_at' => now()->subSecond()]);

    loginWith($token)->assertOk()->assertJsonPath('data.mfa_required', true);
});

test('revoking a device takes effect on the next login', function (): void {
    $user = secUser('trusted@quran.test');
    $token = trustTokenFor($user);

    loginWith($token)->assertJsonStructure(['data' => ['token']]);

    $id = TrustedDeviceModel::query()->where('user_id', $user->id)->value('id');
    $this->actingAs($user)->deleteJson("/api/v1/admin/auth/devices/{$id}")->assertOk();

    loginWith($token)->assertOk()->assertJsonPath('data.mfa_required', true);
});

test('using a trusted device records the use but does not extend the trust', function (): void {
    $user = secUser('trusted@quran.test');
    $token = trustTokenFor($user);

    $before = TrustedDeviceModel::query()->where('user_id', $user->id)->first();
    expect($before->last_used_at)->toBeNull();

    loginWith($token)->assertOk();

    $after = $before->fresh();
    expect($after->last_used_at)->not->toBeNull();

    // The fixed 30-day horizon is the mitigation D5 leans on: a device used
    // daily must still fall out of trust on schedule.
    expect($after->expires_at->toIso8601String())->toBe($before->expires_at->toIso8601String());
});

test('re-trusting the same browser rotates the grant instead of adding one', function (): void {
    $user = secUser('trusted@quran.test');

    $first = trustTokenFor($user, 'browser-1');
    $second = trustTokenFor($user, 'browser-1');

    expect(TrustedDeviceModel::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($second)->not->toBe($first);

    // The superseded token must stop working, or rotation would only add.
    loginWith($first)->assertJsonPath('data.mfa_required', true);
    loginWith($second)->assertJsonStructure(['data' => ['token']]);
});

test('the plaintext token is never stored', function (): void {
    $user = secUser('trusted@quran.test');
    $token = trustTokenFor($user);

    $row = TrustedDeviceModel::query()->where('user_id', $user->id)->first();

    expect($row->token_hash)->toBe(hash('sha256', $token))
        ->and($row->token_hash)->not->toBe($token);

    // And it is not recoverable from the model's array form either.
    expect(json_encode($row->toArray()))->not->toContain($token);
});

test('disabling MFA revokes every trusted device', function (): void {
    $user = secUser('trusted@quran.test');
    trustTokenFor($user, 'browser-1');
    trustTokenFor($user, 'browser-2');

    expect(TrustedDeviceModel::query()->where('user_id', $user->id)->count())->toBe(2);

    $this->actingAs($user)->postJson('/api/v1/admin/auth/mfa/disable', [
        'password' => 'Pass123!',
    ])->assertOk();

    // They existed only to skip a challenge that no longer happens.
    expect(TrustedDeviceModel::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('regenerating recovery codes replaces the old ones', function (): void {
    $user = secUser('trusted@quran.test');
    $user->update(['mfa_recovery_codes' => [password_hash('OLDCODE1', PASSWORD_BCRYPT)]]);

    $response = $this->actingAs($user)->postJson('/api/v1/admin/auth/mfa/recovery-codes', [
        'password' => 'Pass123!',
    ]);

    $response->assertOk();
    expect($response->json('data.recovery_codes'))->toHaveCount(8);

    // The old code must be gone, not appended to: a code the user believes
    // they have spent must not still work.
    $this->actingAs($user)->postJson('/api/v1/admin/auth/mfa/recovery', ['code' => 'OLDCODE1'])
        ->assertStatus(422);

    $fresh = $response->json('data.recovery_codes')[0];
    $this->actingAs($user->fresh())->postJson('/api/v1/admin/auth/mfa/recovery', ['code' => $fresh])
        ->assertOk();
});

test('regenerating recovery codes requires the password', function (): void {
    $user = secUser('trusted@quran.test');

    $this->actingAs($user)->postJson('/api/v1/admin/auth/mfa/recovery-codes', [
        'password' => 'wrong-password',
    ])->assertStatus(422);
});
