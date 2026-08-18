<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\AssignRoleToUserUseCase;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'roles-api');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function roleApiUser(string $email = 'roles-api@quran.test', string $type = UserType::ADMIN): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Roles API',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

/** An admin holding super_admin — the only actor PE-1 lets grant freely. */
function roleApiAdmin(string $email = 'roles-api@quran.test'): UserModel
{
    return withSuperAdmin(roleApiUser($email));
}

function roleApiRoleId(string $name): string
{
    return app(RoleRepositoryContract::class)->findByName($name)->id->value;
}

/*
|--------------------------------------------------------------------------
| The catalogue endpoint
|--------------------------------------------------------------------------
*/

test('the catalogue is returned as a flat list of names', function (): void {
    $response = $this->actingAs(roleApiAdmin())->getJson('/api/v1/admin/permissions');

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('data.permissions'))->toBe(PermissionCatalog::all());
});

test('the catalogue endpoint ships no grouping', function (): void {
    // Grouping is derived from `resource.action` by whoever displays it.
    // Sending groups would mean a new resource needs a server change to
    // become visible, and a translated heading would need a home in the
    // database — the dead end Shaabjo's permission_group table became.
    $data = $this->actingAs(roleApiAdmin())->getJson('/api/v1/admin/permissions')->json('data');

    expect(array_keys($data))->toBe(['permissions']);
});

test('reading the catalogue requires permissions.view', function (): void {
    $this->actingAs(roleApiUser())
        ->getJson('/api/v1/admin/permissions')
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Reading roles
|--------------------------------------------------------------------------
*/

test('the role list carries names, system flags and permissions', function (): void {
    $response = $this->actingAs(roleApiAdmin())->getJson('/api/v1/admin/roles');

    $response->assertOk();
    $names = array_column($response->json('data'), 'name');
    expect($names)->toContain('super_admin', 'competition_manager');

    $superAdmin = collect($response->json('data'))->firstWhere('name', 'super_admin');
    expect($superAdmin['is_system'])->toBeTrue();
    expect($superAdmin['permissions'])->toHaveCount(PermissionCatalog::count());
    expect($superAdmin['permissions_count'])->toBe(PermissionCatalog::count());
});

test('is_system is read from the role, not from a list of names', function (): void {
    // Shaabjo computed this in the resource from a hardcoded array, so its
    // UI claimed eight roles were protected while only one actually was.
    $response = $this->actingAs(roleApiAdmin())->getJson('/api/v1/admin/roles');

    $system = array_filter($response->json('data'), fn (array $r): bool => $r['is_system']);

    expect(array_column($system, 'name'))->toBe(['super_admin']);
});

test('a single role can be read', function (): void {
    $this->actingAs(roleApiAdmin())
        ->getJson('/api/v1/admin/roles/'.roleApiRoleId('moderator'))
        ->assertOk()
        ->assertJsonPath('data.name', 'moderator');
});

test('reading roles requires roles.view', function (): void {
    $this->actingAs(roleApiUser())->getJson('/api/v1/admin/roles')->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Creating
|--------------------------------------------------------------------------
*/

test('a role is created with the permissions it was given', function (): void {
    $response = $this->actingAs(roleApiAdmin())->postJson('/api/v1/admin/roles', [
        'name' => 'content_editor',
        'permissions' => ['content.view', 'content.publish'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'content_editor')
        ->assertJsonPath('data.is_system', false);

    expect($response->json('data.permissions'))->toContain('content.view', 'content.publish');
    expect($response->json('data.permissions'))->toHaveCount(2);
});

test('a role may be created with no permissions at all', function (): void {
    $this->actingAs(roleApiAdmin())
        ->postJson('/api/v1/admin/roles', ['name' => 'empty_role'])
        ->assertCreated()
        ->assertJsonPath('data.permissions', []);
});

test('a permission outside the catalogue is refused', function (): void {
    $this->actingAs(roleApiAdmin())
        ->postJson('/api/v1/admin/roles', ['name' => 'typo_role', 'permissions' => ['seasons.teleport']])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'UNKNOWN_PERMISSION');

    expect(DB::table('roles')->where('name', 'typo_role')->exists())->toBeFalse();
});

test('a duplicate name is a field error, not a crash', function (): void {
    $this->actingAs(roleApiAdmin())
        ->postJson('/api/v1/admin/roles', ['name' => 'moderator'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('a name the domain rejects is reported as such', function (): void {
    // The domain requires snake_case. Presentation deliberately does not
    // re-state that rule, so this arrives as a domain refusal rather than
    // as a validation error from a regex that could drift from it.
    $this->actingAs(roleApiAdmin())
        ->postJson('/api/v1/admin/roles', ['name' => 'Content Editor'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_ROLE_NAME');
});

test('creating a role requires roles.create', function (): void {
    $this->actingAs(roleApiUser())
        ->postJson('/api/v1/admin/roles', ['name' => 'nope'])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| The identity surface is separate from running the competition
|--------------------------------------------------------------------------
*/

test('competition_manager cannot reach the role editor', function (): void {
    // ADR-015 §7.3 separates "runs the competition" from "controls who may
    // run it". This is that separation, asserted through HTTP.
    $granter = roleApiAdmin('granter@quran.test');
    $manager = roleApiUser('manager@quran.test');

    app(AssignRoleToUserUseCase::class)
        ->execute((string) $manager->id, roleApiRoleId('competition_manager'), (string) $granter->id);

    $this->actingAs($manager)->getJson('/api/v1/admin/roles')->assertForbidden();
    $this->actingAs($manager)
        ->postJson('/api/v1/admin/roles', ['name' => 'sneaky', 'permissions' => ['users.delete']])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Editing permissions
|--------------------------------------------------------------------------
*/

test('a role permission set is replaced by what was sent', function (): void {
    $roleId = roleApiRoleId('moderator');

    $response = $this->actingAs(roleApiAdmin())
        ->patchJson("/api/v1/admin/roles/{$roleId}/permissions", [
            'permissions' => ['content.view'],
        ]);

    $response->assertOk();
    expect($response->json('data.permissions'))->toBe(['content.view']);
});

test('a role can be emptied', function (): void {
    // `present` and not `required` in the request, because clearing a role
    // is a legitimate edit and `required` rejects an empty array.
    $roleId = roleApiRoleId('moderator');

    $this->actingAs(roleApiAdmin())
        ->patchJson("/api/v1/admin/roles/{$roleId}/permissions", ['permissions' => []])
        ->assertOk()
        ->assertJsonPath('data.permissions', []);
});

test('editing a system role permission set is refused', function (): void {
    $roleId = roleApiRoleId('super_admin');

    $this->actingAs(roleApiAdmin())
        ->patchJson("/api/v1/admin/roles/{$roleId}/permissions", ['permissions' => ['seasons.view']])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'SYSTEM_ROLE_IMMUTABLE');
});

test('an unknown permission is refused on edit too', function (): void {
    $roleId = roleApiRoleId('moderator');

    $this->actingAs(roleApiAdmin())
        ->patchJson("/api/v1/admin/roles/{$roleId}/permissions", ['permissions' => ['not_a.thing']])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'UNKNOWN_PERMISSION');
});

test('editing permissions takes effect on the very next request', function (): void {
    // The cache is not the correctness mechanism; invalidation is. Asserted
    // through HTTP rather than by calling the resolver, because that is
    // where a missed invalidation would actually be felt.
    $admin = roleApiAdmin();
    $subject = roleApiUser('subject@quran.test');

    $role = app(CreateRoleUseCase::class)->execute('role_reader', ['roles.view'], (string) $admin->id);
    app(AssignRoleToUserUseCase::class)
        ->execute((string) $subject->id, $role->id->value, (string) $admin->id);

    $this->actingAs($subject)->getJson('/api/v1/admin/roles')->assertOk();

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/roles/{$role->id->value}/permissions", ['permissions' => []])
        ->assertOk();

    $this->actingAs($subject)->getJson('/api/v1/admin/roles')->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| roles.update is not roles.grant_permissions
|--------------------------------------------------------------------------
*/

test('holding roles.update lets you rename a role but not change what it can do', function (): void {
    // The separation this permission exists for. Renaming is cosmetic;
    // editing the permission set is the escalation surface. PE-2 stops an
    // actor granting beyond themselves, but it is not a substitute for
    // separating the two capabilities — a super_admin-adjacent operator
    // trusted to tidy labels should not thereby be able to hand out
    // capability they happen to hold.
    $admin = roleApiAdmin();
    $editor = roleApiUser('label-editor@quran.test');

    $limited = app(CreateRoleUseCase::class)
        ->execute('role_label_editor', ['roles.update'], (string) $admin->id);
    app(AssignRoleToUserUseCase::class)
        ->execute((string) $editor->id, $limited->id->value, (string) $admin->id);

    $target = roleApiRoleId('moderator');

    $this->actingAs($editor)
        ->patchJson("/api/v1/admin/roles/{$target}", ['name' => 'renamed_by_editor'])
        ->assertOk()
        ->assertJsonPath('data.name', 'renamed_by_editor');

    $this->actingAs($editor)
        ->patchJson("/api/v1/admin/roles/{$target}/permissions", ['permissions' => ['users.delete']])
        ->assertForbidden();
});

test('holding roles.grant_permissions does not let you rename', function (): void {
    // The other direction, so the two are proved independent rather than
    // one being a superset of the other by accident.
    $admin = roleApiAdmin();
    $granter = roleApiUser('granter-only@quran.test');

    $limited = app(CreateRoleUseCase::class)
        ->execute('role_granter', ['roles.grant_permissions'], (string) $admin->id);
    app(AssignRoleToUserUseCase::class)
        ->execute((string) $granter->id, $limited->id->value, (string) $admin->id);

    $target = roleApiRoleId('moderator');

    $this->actingAs($granter)
        ->patchJson("/api/v1/admin/roles/{$target}", ['name' => 'renamed_by_granter'])
        ->assertForbidden();
});

test('a new catalogue permission reaches super_admin without a hand edit', function (): void {
    // RolesSeeder re-syncs system roles from PermissionCatalog::all() rather
    // than a written list, which is the whole reason roles.grant_permissions
    // needed no seeder change when it was added.
    $held = app(RoleRepositoryContract::class)->findByName('super_admin')->getPermissionNames();

    expect($held)->toContain('roles.grant_permissions');
});

test('no editable role was handed the new permission silently', function (): void {
    // The five operator-owned roles are starting points created once. None
    // of them holds any roles.* capability, and adding a catalogue entry
    // must not change that.
    foreach (['competition_manager', 'moderator'] as $name) {
        $held = app(RoleRepositoryContract::class)->findByName($name)->getPermissionNames();

        expect($held)->not->toContain('roles.grant_permissions', 'roles.update');
    }
});

/*
|--------------------------------------------------------------------------
| Renaming and deleting
|--------------------------------------------------------------------------
*/

test('a role can be renamed', function (): void {
    $roleId = roleApiRoleId('moderator');

    $this->actingAs(roleApiAdmin())
        ->patchJson("/api/v1/admin/roles/{$roleId}", ['name' => 'community_moderator'])
        ->assertOk()
        ->assertJsonPath('data.name', 'community_moderator');
});

test('renaming a role to the name it already has is not a collision with itself', function (): void {
    $roleId = roleApiRoleId('moderator');

    $this->actingAs(roleApiAdmin())
        ->patchJson("/api/v1/admin/roles/{$roleId}", ['name' => 'moderator'])
        ->assertOk();
});

test('a system role cannot be renamed', function (): void {
    $roleId = roleApiRoleId('super_admin');

    $this->actingAs(roleApiAdmin())
        ->patchJson("/api/v1/admin/roles/{$roleId}", ['name' => 'root'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'SYSTEM_ROLE_IMMUTABLE');
});

test('a role can be deleted', function (): void {
    $admin = roleApiAdmin();
    $role = app(CreateRoleUseCase::class)->execute('temporary_role', [], (string) $admin->id);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/roles/{$role->id->value}")
        ->assertOk();

    expect(DB::table('roles')->where('name', 'temporary_role')->exists())->toBeFalse();
});

test('a system role cannot be deleted', function (): void {
    $this->actingAs(roleApiAdmin())
        ->deleteJson('/api/v1/admin/roles/'.roleApiRoleId('super_admin'))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'SYSTEM_ROLE_IMMUTABLE');
});

test('deleting a role revokes it from its holders on the next request', function (): void {
    $admin = roleApiAdmin();
    $subject = roleApiUser('holder@quran.test');

    $role = app(CreateRoleUseCase::class)->execute('doomed_role', ['roles.view'], (string) $admin->id);
    app(AssignRoleToUserUseCase::class)
        ->execute((string) $subject->id, $role->id->value, (string) $admin->id);

    $this->actingAs($subject)->getJson('/api/v1/admin/roles')->assertOk();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/roles/{$role->id->value}")->assertOk();

    // The holders are captured before the pivot rows go; asking afterwards
    // finds nobody and leaves a stale cache entry authorizing someone whose
    // role no longer exists.
    $this->actingAs($subject)->getJson('/api/v1/admin/roles')->assertForbidden();
});

test('deleting a role requires roles.delete', function (): void {
    $this->actingAs(roleApiUser())
        ->deleteJson('/api/v1/admin/roles/'.roleApiRoleId('moderator'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Surface
|--------------------------------------------------------------------------
*/

test('a contestant cannot reach the identity surface at all', function (): void {
    // The type gate, not the permission gate — EnsureUserIsAdmin answers
    // this before any ability is consulted (ADR-015 §1).
    $contestant = roleApiUser('contestant@quran.test', UserType::CONTESTANT);

    $this->actingAs($contestant)->getJson('/api/v1/admin/roles')->assertForbidden();
    $this->actingAs($contestant)->getJson('/api/v1/admin/permissions')->assertForbidden();
});
