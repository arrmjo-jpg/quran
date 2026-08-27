<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;
use Symfony\Component\Uid\Uuid;

uses(RefreshDatabase::class)->group('core', 'feature', 'security');

/*
|--------------------------------------------------------------------------
| Login history — ADR-018 D2, D3, D10
|--------------------------------------------------------------------------
|
| The rows are not created by this feature; they are created by
| AuditLoggingMiddleware as a side effect of the login request. So these
| tests log in for real rather than inserting fixtures: a test that seeded
| audit_logs directly would pass even if the middleware stopped recording.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function historyUser(string $email = 'history@quran.test'): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Uuid::v7(),
        'email' => $email,
        'name' => 'History User',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function attemptLogin(string $email, string $password): void
{
    test()->postJson('/api/v1/admin/auth/login', ['email' => $email, 'password' => $password]);
}

test('security.view is required to read the login history', function (): void {
    $viewer = historyUser('nobody@quran.test');

    $this->actingAs($viewer)
        ->getJson('/api/v1/admin/security/login-history')
        ->assertForbidden();
});

test('audit.view alone does not grant the login history', function (): void {
    // ADR-018 D3's entire point. If this ever passes with 200, the two
    // permissions have been collapsed and a grant issued for the activity
    // feed is silently showing every login attempt on the platform.
    expect(PermissionCatalog::all())->toContain('security.view')
        ->and(PermissionCatalog::all())->toContain('audit.view');

    $viewer = historyUser('auditor@quran.test');
    $roleId = (string) Uuid::v7();
    DB::table('roles')->insert([
        'id' => $roleId, 'name' => 'auditor_only', 'is_system' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $permissionId = DB::table('permissions')->where('name', 'audit.view')->value('id');
    DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $roleId]);
    DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $viewer->id]);

    $this->actingAs($viewer->fresh())
        ->getJson('/api/v1/admin/security/login-history')
        ->assertForbidden();
});

test('a successful login appears, attributed to the account', function (): void {
    $user = historyUser();
    attemptLogin('history@quran.test', 'Pass123!');

    $response = $this->actingAs(withSuperAdmin(historyUser('admin@quran.test')))
        ->getJson('/api/v1/admin/security/login-history');

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('outcome', 'success');

    expect($row)->not->toBeNull()
        ->and($row['status'])->toBe(200)
        // ADR-018 D10 -- this was null on every login row before this epic.
        ->and($row['user_id'])->toBe($user->id);
});

test('a wrong password is recorded against the account it targeted', function (): void {
    $user = historyUser();
    attemptLogin('history@quran.test', 'wrong-password');

    $response = $this->actingAs(withSuperAdmin(historyUser('admin@quran.test')))
        ->getJson('/api/v1/admin/security/login-history');

    $row = collect($response->json('data'))->firstWhere('outcome', 'invalid_credentials');

    // The row a login history most needs to show: somebody has been trying
    // this account. Attribution happens before the password is checked (D10).
    expect($row)->not->toBeNull()
        ->and($row['status'])->toBe(401)
        ->and($row['user_id'])->toBe($user->id);
});

test('an attempt on an unknown address names no account and echoes no email', function (): void {
    attemptLogin('does-not-exist@quran.test', 'Pass123!');

    $response = $this->actingAs(withSuperAdmin(historyUser('admin@quran.test')))
        ->getJson('/api/v1/admin/security/login-history');

    $row = collect($response->json('data'))->firstWhere('outcome', 'invalid_credentials');

    expect($row)->not->toBeNull()
        ->and($row['user_id'])->toBeNull();

    // A security screen must not become a list of the addresses somebody
    // guessed. The whole response is checked, not just the row.
    expect(json_encode($response->json()))->not->toContain('does-not-exist@quran.test');
});

test('the response carries no password and no token', function (): void {
    historyUser();
    attemptLogin('history@quran.test', 'Pass123!');
    attemptLogin('history@quran.test', 'hunter2-is-a-secret');

    $body = json_encode($this->actingAs(withSuperAdmin(historyUser('admin@quran.test')))
        ->getJson('/api/v1/admin/security/login-history')->json());

    expect($body)->not->toContain('hunter2-is-a-secret');
    expect($body)->not->toContain('Pass123!');
    expect($body)->not->toContain('password');
});

test('outcomes are filterable, and an outcome nobody produced returns nothing', function (): void {
    historyUser();
    attemptLogin('history@quran.test', 'Pass123!');
    attemptLogin('history@quran.test', 'wrong-password');

    $admin = withSuperAdmin(historyUser('admin@quran.test'));

    $success = $this->actingAs($admin)
        ->getJson('/api/v1/admin/security/login-history?outcome=success');
    $success->assertOk();
    expect(collect($success->json('data'))->pluck('outcome')->unique()->all())->toBe(['success']);

    // Nobody was rate limited here. The filter must return an empty page
    // rather than falling through to everything.
    $none = $this->actingAs($admin)
        ->getJson('/api/v1/admin/security/login-history?outcome=rate_limited');
    $none->assertOk();
    expect($none->json('data'))->toBe([]);
});

test('an outcome outside the vocabulary is refused, not silently ignored', function (): void {
    $admin = withSuperAdmin(historyUser('admin@quran.test'));

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/security/login-history?outcome=definitely-not-an-outcome')
        ->assertStatus(422);
});

test('history can be narrowed to one account', function (): void {
    $one = historyUser('one@quran.test');
    historyUser('two@quran.test');

    attemptLogin('one@quran.test', 'Pass123!');
    attemptLogin('two@quran.test', 'Pass123!');

    $response = $this->actingAs(withSuperAdmin(historyUser('admin@quran.test')))
        ->getJson('/api/v1/admin/security/login-history?user_id='.$one->id);

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('user_id')->unique()->all())->toBe([$one->id]);
});

test('names are resolved once for the page, beside the rows and not on them', function (): void {
    $user = historyUser();
    attemptLogin('history@quran.test', 'Pass123!');

    $response = $this->actingAs(withSuperAdmin(historyUser('admin@quran.test')))
        ->getJson('/api/v1/admin/security/login-history');

    $response->assertOk()->assertJsonPath("users.{$user->id}", 'History User');

    // Not repeated onto every row -- one person's forty attempts this morning
    // should not carry forty copies of their name.
    expect($response->json('data.0'))->not->toHaveKey('user_name');
});
