<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Security\TotpService;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class)->group('core', 'feature', 'security', 'golden-master');

/*
|--------------------------------------------------------------------------
| Account security — the contracts as they stand BEFORE Epic 6
|--------------------------------------------------------------------------
|
| Epic 6 changes four things that other code already depends on:
|
|   * login gains a trusted-device branch  (ADR-018 D5)
|   * device trust moves from Cache to a table, and becomes a credential
|     returned once instead of a field returned on every read (D1, D5)
|   * a `security.view`-gated login history appears over audit_logs (D2, D3)
|   * MFA gains a way to be turned off (D4)
|
| This file records what those endpoints do today, so each change shows up
| as a diff against a recorded expectation rather than as a test written
| afterwards to match whatever came out.
|
| WHY NOW. DeviceTrustTest asserts three happy paths and pins the response
| KEYS but nothing about behaviour: it never asks whether trusting a device
| does anything. MfaAndSessionsTest never asks whether MFA can be switched
| off. Both blind spots are exactly what Epic 6 changes, so recording them
| after the change would prove nothing.
|
| THESE ASSERTIONS ARE DESCRIPTIVE, NOT PRESCRIPTIVE. Several record
| behaviour ADR-018 calls defective and sets out to change -- trust that
| affects nothing, a secret returned on every read, a cache flush that
| silently revokes every device. Recording them is not endorsing them; it
| is refusing to change them silently.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
    Cache::flush();
});

function gmSecurityUser(bool $mfa = false): UserModel
{
    $user = UserModel::query()->create([
        'id' => (string) Uuid::v7(),
        'email' => 'gm-security@quran.test',
        'name' => 'Golden Master Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    if ($mfa) {
        $user->update([
            'mfa_enabled' => true,
            'mfa_secret' => Crypt::encryptString(app(TotpService::class)->generateSecret()),
        ]);
    }

    return withSuperAdmin($user->fresh());
}

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

test('GOLDEN MASTER: login without MFA returns a token and the user', function (): void {
    gmSecurityUser();

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'gm-security@quran.test',
        'password' => 'Pass123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token', 'user']]);

    // BEFORE Epic 6: no device is consulted, so no trust-related key appears.
    expect($response->json('data'))->not->toHaveKey('mfa_required');
})->group('golden-master');

test('GOLDEN MASTER: login with MFA enabled always issues a challenge', function (): void {
    gmSecurityUser(mfa: true);

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'gm-security@quran.test',
        'password' => 'Pass123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.mfa_required', true)
        ->assertJsonStructure(['data' => ['mfa_required', 'challenge_token']]);

    // BEFORE Epic 6: there is no `token` here -- the challenge must be
    // completed first. AFTER Epic 6 this stays true for an UNTRUSTED device;
    // a trusted one receives a full token instead (ADR-018 D5).
    expect($response->json('data'))->not->toHaveKey('token');
})->group('golden-master');

/*
|--------------------------------------------------------------------------
| Trusted devices
|--------------------------------------------------------------------------
*/

test('GOLDEN MASTER: trusting a device returns the token on every read', function (): void {
    $user = gmSecurityUser();

    $trust = $this->actingAs($user)
        ->withHeaders(['X-Device-ID' => 'gm-device-1', 'User-Agent' => 'Mozilla/5.0'])
        ->postJson('/api/v1/admin/auth/devices/trust');

    $trust->assertOk()->assertJsonStructure([
        'data' => ['id', 'user_id', 'trust_token', 'ip', 'user_agent', 'trusted_at', 'expires_at'],
    ]);

    // BEFORE Epic 6: the secret comes back again from the LIST endpoint, on
    // every read, for every device. ADR-018 D5 returns it exactly once and
    // stores only a hash.
    $list = $this->actingAs($user)->getJson('/api/v1/admin/auth/devices');
    $list->assertOk();
    expect($list->json('data.0'))->toHaveKey('trust_token');
})->group('golden-master');

test('GOLDEN MASTER: a trusted device does not affect authentication', function (): void {
    $user = gmSecurityUser(mfa: true);

    $this->actingAs($user)
        ->withHeaders(['X-Device-ID' => 'gm-device-trusted', 'User-Agent' => 'Mozilla/5.0'])
        ->postJson('/api/v1/admin/auth/devices/trust')
        ->assertOk();

    // BEFORE Epic 6: logging in from the very device just trusted still gets
    // an MFA challenge, because login never consults the service. This is the
    // defect ADR-018 D5 exists to fix -- recorded, not endorsed.
    $login = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'gm-security@quran.test',
        'password' => 'Pass123!',
    ]);

    $login->assertOk()->assertJsonPath('data.mfa_required', true);
})->group('golden-master');

test('GOLDEN MASTER: clearing the cache silently revokes every trusted device', function (): void {
    $user = gmSecurityUser();

    $this->actingAs($user)
        ->withHeaders(['X-Device-ID' => 'gm-device-fragile'])
        ->postJson('/api/v1/admin/auth/devices/trust')
        ->assertOk();

    $this->actingAs($user)->getJson('/api/v1/admin/auth/devices')
        ->assertOk()->assertJsonCount(1, 'data');

    Cache::flush();

    // BEFORE Epic 6: a routine maintenance command destroys a security
    // decision the user made deliberately. ADR-018 D1's first reason.
    $this->actingAs($user)->getJson('/api/v1/admin/auth/devices')
        ->assertOk()->assertJsonCount(0, 'data');
})->group('golden-master');

/*
|--------------------------------------------------------------------------
| MFA
|--------------------------------------------------------------------------
*/

test('GOLDEN MASTER: MFA cannot be turned off once enabled', function (): void {
    $user = gmSecurityUser(mfa: true);

    // BEFORE Epic 6: no route exists to disable MFA, so an account that
    // enables it is committed permanently. ADR-018 D4 -- an optional feature
    // you cannot leave is not optional.
    $response = $this->actingAs($user)->postJson('/api/v1/admin/auth/mfa/disable', [
        'password' => 'Pass123!',
    ]);

    expect($response->status())->toBeIn([404, 405]);
    expect($user->fresh()->mfa_enabled)->toBeTrue();
})->group('golden-master');

/*
|--------------------------------------------------------------------------
| Login history
|--------------------------------------------------------------------------
*/

test('GOLDEN MASTER: audit_logs already records login attempts, and nothing reads them', function (): void {
    $user = gmSecurityUser();

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'gm-security@quran.test',
        'password' => 'Pass123!',
    ])->assertOk();

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'gm-security@quran.test',
        'password' => 'wrong-password',
    ])->assertStatus(401);

    // BEFORE Epic 6: both the success and the failure are already stored --
    // this is why ADR-018 D2 reads the history rather than writing a second
    // one. Asserted on the route name, not the path: the public and admin
    // login routes have different paths and stable names.
    $rows = DB::table('audit_logs')->where('route_name', 'admin.auth.login')->get();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('response_status')->all())->toEqualCanonicalizing([200, 401]);

    // ...and device_id is null, because nothing sends the header (D7).
    expect($rows->pluck('device_id')->unique()->all())->toBe([null]);

    // No endpoint serves any of it.
    $this->actingAs($user)
        ->getJson('/api/v1/admin/security/login-history')
        ->assertNotFound();
})->group('golden-master');
