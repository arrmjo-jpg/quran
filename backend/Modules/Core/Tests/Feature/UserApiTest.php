<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'users-api');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function userApiUser(
    string $email,
    string $type = UserType::ADMIN,
    bool $active = true
): UserModel {
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Account '.$email,
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => $active,
    ]);
}

function userApiAdmin(string $email = 'users-api@quran.test'): UserModel
{
    return withSuperAdmin(userApiUser($email));
}

function userApiRoleId(string $name): string
{
    return app(RoleRepositoryContract::class)->findByName($name)->id->value;
}

/*
|--------------------------------------------------------------------------
| Listing
|--------------------------------------------------------------------------
*/

test('the list is paginated and carries roles without a query per row', function (): void {
    $admin = userApiAdmin();
    for ($i = 0; $i < 3; $i++) {
        userApiUser("listed-{$i}@quran.test", UserType::CONTESTANT);
    }

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?per_page=2');

    $response->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.total', 4);

    expect($response->json('data'))->toHaveCount(2);
});

test('the list reports membership, not effective permissions', function (): void {
    // The whole reason AdminUserResource exists: permissionsOf() costs a
    // resolution per account, and a page of twenty would pay it twenty times
    // for a column nobody reads at a glance.
    $admin = userApiAdmin();

    $row = collect($this->actingAs($admin)->getJson('/api/v1/admin/users')->json('data'))
        ->firstWhere('email', 'users-api@quran.test');

    expect($row['roles'])->toBe(['super_admin']);
    expect(array_key_exists('permissions', $row))->toBeFalse();
});

test('a single account answers what it can actually do', function (): void {
    $admin = userApiAdmin();

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/users/'.$admin->id);

    $response->assertOk()->assertJsonPath('data.roles', ['super_admin']);
    expect($response->json('data.permissions'))->toContain('users.view', 'roles.view');
});

test('the list can be filtered by type, state and role', function (): void {
    $admin = userApiAdmin();
    userApiUser('a-contestant@quran.test', UserType::CONTESTANT);
    userApiUser('inactive-admin@quran.test', UserType::ADMIN, active: false);

    $byType = $this->actingAs($admin)->getJson('/api/v1/admin/users?type=contestant')->json('data');
    expect(array_column($byType, 'email'))->toBe(['a-contestant@quran.test']);

    $inactive = $this->actingAs($admin)->getJson('/api/v1/admin/users?is_active=0')->json('data');
    expect(array_column($inactive, 'email'))->toBe(['inactive-admin@quran.test']);

    $byRole = $this->actingAs($admin)->getJson('/api/v1/admin/users?role=super_admin')->json('data');
    expect(array_column($byRole, 'email'))->toBe(['users-api@quran.test']);
});

test('search matches name or email', function (): void {
    $admin = userApiAdmin();
    userApiUser('findme@quran.test', UserType::CONTESTANT);

    $found = $this->actingAs($admin)->getJson('/api/v1/admin/users?search=findme')->json('data');

    expect(array_column($found, 'email'))->toBe(['findme@quran.test']);
});

test('reading accounts requires users.view', function (): void {
    $this->actingAs(userApiUser('nobody@quran.test'))
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Roles
|--------------------------------------------------------------------------
*/

test('roles can be assigned and revoked as a final set', function (): void {
    $admin = userApiAdmin();
    $subject = userApiUser('subject@quran.test');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$subject->id}/roles", [
            'roles' => [userApiRoleId('moderator')],
        ])
        ->assertOk()
        ->assertJsonPath('data.roles', ['moderator']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$subject->id}/roles", ['roles' => []])
        ->assertOk()
        ->assertJsonPath('data.roles', []);
});

test('assigning roles requires users.assign_roles, not users.update', function (): void {
    // The same separation as roles.update vs roles.grant_permissions:
    // handing an account a role is the escalation surface PE-1 guards,
    // editing its name is not.
    $admin = userApiAdmin();
    $subject = userApiUser('target@quran.test');

    $limited = app(Modules\Core\Application\UseCases\CreateRoleUseCase::class)
        ->execute('account_editor', ['users.view', 'users.update'], (string) $admin->id);
    $editor = userApiUser('editor@quran.test');
    app(Modules\Core\Application\UseCases\AssignRoleToUserUseCase::class)
        ->execute((string) $editor->id, $limited->id->value, (string) $admin->id);

    $this->actingAs($editor)->getJson('/api/v1/admin/users')->assertOk();
    $this->actingAs($editor)
        ->patchJson("/api/v1/admin/users/{$subject->id}/roles", ['roles' => []])
        ->assertForbidden();
});

test('users.assign_roles confers no other account capability', function (): void {
    // The other direction of the separation above, as far as it can be
    // asserted: `users.update` guards no route yet — there is no endpoint
    // that edits an account's name or email — so "an assigner cannot edit
    // user data" has nothing to send a request to. What CAN be proved is
    // that holding assign_roles grants none of the capabilities that do
    // exist, which is the same claim against the endpoints that are real.
    $admin = userApiAdmin();
    $subject = userApiUser('assign-target@quran.test');
    $other = withSuperAdmin(userApiUser('another-super@quran.test'));

    $assigner = userApiUser('assigner@quran.test');
    $role = app(Modules\Core\Application\UseCases\CreateRoleUseCase::class)
        ->execute('assigner_role', ['users.view', 'users.assign_roles'], (string) $admin->id);
    app(Modules\Core\Application\UseCases\AssignRoleToUserUseCase::class)
        ->execute((string) $assigner->id, $role->id->value, (string) $admin->id);

    // The capability it does hold.
    $this->actingAs($assigner)
        ->patchJson("/api/v1/admin/users/{$subject->id}/roles", ['roles' => []])
        ->assertOk();

    // The ones it does not.
    $this->actingAs($assigner)
        ->patchJson("/api/v1/admin/users/{$other->id}/deactivate")
        ->assertForbidden();
    $this->actingAs($assigner)
        ->patchJson("/api/v1/admin/users/{$other->id}/activate")
        ->assertForbidden();
});

test('an account cannot change its own roles', function (): void {
    // PE-3. Acquiring capability always leaves a trace involving two people.
    $admin = userApiAdmin();

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$admin->id}/roles", ['roles' => []])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'SELF_ROLE_CHANGE');
});

test('an actor cannot grant a role exceeding their own permissions', function (): void {
    // PE-1, through HTTP.
    $admin = userApiAdmin();
    $granter = userApiUser('granter@quran.test');
    $subject = userApiUser('escalation-target@quran.test');

    $weak = app(Modules\Core\Application\UseCases\CreateRoleUseCase::class)
        ->execute('assigner_only', ['users.view', 'users.assign_roles'], (string) $admin->id);
    app(Modules\Core\Application\UseCases\AssignRoleToUserUseCase::class)
        ->execute((string) $granter->id, $weak->id->value, (string) $admin->id);

    $this->actingAs($granter)
        ->patchJson("/api/v1/admin/users/{$subject->id}/roles", [
            'roles' => [userApiRoleId('super_admin')],
        ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'PRIVILEGE_ESCALATION');
});

/*
|--------------------------------------------------------------------------
| Activation — the door PE-5 did not previously cover
|--------------------------------------------------------------------------
*/

test('an account can be deactivated and reactivated', function (): void {
    $admin = userApiAdmin();
    $second = withSuperAdmin(userApiUser('second-super@quran.test'));

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$second->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$second->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

test('a deactivated account is refused at the door on its next request', function (): void {
    $admin = userApiAdmin();
    $second = withSuperAdmin(userApiUser('soon-disabled@quran.test'));

    $this->actingAs($second)->getJson('/api/v1/admin/users')->assertOk();

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$second->id}/deactivate")->assertOk();

    // Its roles are untouched and still correct; it is simply not permitted
    // to act on them. That is why authorization is not resolution.
    $this->actingAs($second->fresh())->getJson('/api/v1/admin/users')->assertForbidden();
});

test('deactivating the last active holder of a system role is refused', function (): void {
    // THE HOLE THIS SLICE WOULD OTHERWISE OPEN. PE-5 guarded role removal
    // from the last active holder; deactivation reaches the same lockout by
    // another door, and a deactivated account is allowed nothing — so nobody
    // could administer the platform and nobody could reactivate them, because
    // reactivation is itself a permission. Recovery would be a database edit.
    $actor = userApiAdmin('actor@quran.test');
    $lastHolder = withSuperAdmin(userApiUser('last-super@quran.test'));

    // The actor keeps its own super_admin only long enough to be allowed to
    // act; stripping it leaves exactly one holder, which is the situation
    // under test. Revoking still works while holding nothing — PE-1 checks
    // only the roles being added.
    $role = app(Modules\Core\Application\UseCases\CreateRoleUseCase::class)
        ->execute('account_operator', ['users.view', 'users.deactivate'], (string) $actor->id);
    revokeAllRoles((string) $actor->id);
    app(Modules\Core\Application\UseCases\AssignRoleToUserUseCase::class)
        ->execute((string) $actor->id, $role->id->value, (string) $lastHolder->id);

    $this->actingAs($actor->fresh())
        ->patchJson("/api/v1/admin/users/{$lastHolder->id}/deactivate")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'LAST_SYSTEM_ROLE_HOLDER');

    expect(UserModel::query()->find($lastHolder->id)->is_active)->toBeTrue();
});

test('deactivating a system role holder is allowed while another remains active', function (): void {
    // The other half: the guard must protect the last one, not every one.
    $actor = userApiAdmin('actor@quran.test');
    $second = withSuperAdmin(userApiUser('second-super@quran.test'));

    $this->actingAs($actor)
        ->patchJson("/api/v1/admin/users/{$second->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

test('an account cannot deactivate itself', function (): void {
    // Independent of PE-5: refused even with other administrators active,
    // because a live session disabling the account it runs as is a confusing
    // state whatever else is true.
    $admin = userApiAdmin();
    withSuperAdmin(userApiUser('someone-else@quran.test'));

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$admin->id}/deactivate")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'SELF_DEACTIVATION');

    expect(UserModel::query()->find($admin->id)->is_active)->toBeTrue();
});

test('activation and deactivation are separately grantable', function (): void {
    $admin = userApiAdmin();
    $second = withSuperAdmin(userApiUser('second-super@quran.test'));

    $reactivator = userApiUser('reactivator@quran.test');
    $role = app(Modules\Core\Application\UseCases\CreateRoleUseCase::class)
        ->execute('reactivator_only', ['users.view', 'users.activate'], (string) $admin->id);
    app(Modules\Core\Application\UseCases\AssignRoleToUserUseCase::class)
        ->execute((string) $reactivator->id, $role->id->value, (string) $admin->id);

    $this->actingAs($reactivator)
        ->patchJson("/api/v1/admin/users/{$second->id}/deactivate")
        ->assertForbidden();

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$second->id}/deactivate")->assertOk();

    $this->actingAs($reactivator)
        ->patchJson("/api/v1/admin/users/{$second->id}/activate")
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Surface
|--------------------------------------------------------------------------
*/

test('a contestant cannot reach account administration', function (): void {
    $contestant = userApiUser('contestant@quran.test', UserType::CONTESTANT);

    $this->actingAs($contestant)->getJson('/api/v1/admin/users')->assertForbidden();
});
