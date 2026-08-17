<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Application\UseCases\DeleteRoleUseCase;
use Modules\Core\Application\UseCases\RenameRoleUseCase;
use Modules\Core\Application\UseCases\SyncRolePermissionsUseCase;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Events\RoleCreated;
use Modules\Core\Domain\Events\RoleDeleted;
use Modules\Core\Domain\Events\RolePermissionsChanged;
use Modules\Core\Domain\Events\RoleRenamed;
use Modules\Core\Domain\Exceptions\SystemRoleImmutableException;
use Modules\Core\Domain\Exceptions\UnknownPermissionException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\PermissionName;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'roles');

beforeEach(function (): void {
    // Roles can only be granted permissions that exist as rows, so the
    // catalogue has to be seeded before any of this is meaningful.
    (new PermissionsSeeder)->run();
});

/**
 * A real acting administrator.
 *
 * These tests passed the literal string "admin-1" as an actor id. That
 * was harmless while the id was only copied into a domain event, but
 * PE-2 now builds a UserId from it to resolve what the actor holds, and
 * "admin-1" is not a UUID — so the fixture has to name someone who
 * actually exists and actually holds something.
 *
 * Idempotent: it is called inline at several call sites, and a test that
 * needs an actor twice should get the same one rather than a duplicate
 * key error.
 */
function roleUseCasesActor(): string
{
    $existing = UserModel::query()->where('email', 'role-actor@quran.test')->first();

    if ($existing !== null) {
        return (string) $existing->id;
    }

    app(RolesSeeder::class)->run();

    $id = (string) Str::uuid();

    UserModel::query()->create([
        'id' => $id,
        'email' => 'role-actor@quran.test',
        'name' => 'Role Actor',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    return grantSuperAdmin($id);
}

test('CreateRoleUseCase persists a custom role and dispatches RoleCreated', function (): void {
    Event::fake();

    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view', 'content.publish'], roleUseCasesActor());

    expect($role->isSystem())->toBeFalse();
    expect($role->getPermissionNames())->toBe(['content.view', 'content.publish']);

    $reloaded = app(RoleRepositoryContract::class)->findByName('content_editor');
    expect($reloaded)->not->toBeNull();
    expect($reloaded->getPermissionNames())->toBe(['content.publish', 'content.view']); // repo orders by name

    Event::assertDispatched(RoleCreated::class);
});

test('CreateRoleUseCase rejects a permission outside the catalogue', function (): void {
    expect(fn () => app(CreateRoleUseCase::class)->execute('bad_role', ['content.view', 'seasons.teleport']))
        ->toThrow(UnknownPermissionException::class);

    // Rolled back — no half-created role.
    expect(app(RoleRepositoryContract::class)->findByName('bad_role'))->toBeNull();
});

test('CreateRoleUseCase rejects a duplicate name', function (): void {
    app(CreateRoleUseCase::class)->execute('content_editor');

    expect(fn () => app(CreateRoleUseCase::class)->execute('content_editor'))
        ->toThrow(RuntimeException::class);
});

test('CreateRoleUseCase cannot create a system role', function (): void {
    // is_system is not a parameter at all — the guarantee is structural.
    $reflection = new ReflectionMethod(CreateRoleUseCase::class, 'execute');
    $params = array_map(static fn (ReflectionParameter $p): string => $p->getName(), $reflection->getParameters());

    expect($params)->not->toContain('isSystem');

    $role = app(CreateRoleUseCase::class)->execute('content_editor');
    expect($role->isSystem())->toBeFalse();
});

test('RenameRoleUseCase renames a custom role and dispatches RoleRenamed', function (): void {
    Event::fake();
    $role = app(CreateRoleUseCase::class)->execute('content_editor');

    $renamed = app(RenameRoleUseCase::class)->execute($role->id->value, 'cms_editor', roleUseCasesActor());

    expect($renamed->getName())->toBe('cms_editor');
    expect(app(RoleRepositoryContract::class)->findByName('cms_editor'))->not->toBeNull();
    expect(app(RoleRepositoryContract::class)->findByName('content_editor'))->toBeNull();

    Event::assertDispatched(RoleRenamed::class);
});

test('RenameRoleUseCase rejects renaming onto an existing name', function (): void {
    app(CreateRoleUseCase::class)->execute('content_editor');
    $other = app(CreateRoleUseCase::class)->execute('media_editor');

    expect(fn () => app(RenameRoleUseCase::class)->execute($other->id->value, 'content_editor'))
        ->toThrow(RuntimeException::class);
});

test('DeleteRoleUseCase removes the role and its grants, dispatching RoleDeleted with them', function (): void {
    Event::fake();
    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view', 'content.publish']);

    app(DeleteRoleUseCase::class)->execute($role->id->value, roleUseCasesActor());

    expect(app(RoleRepositoryContract::class)->find($role->id))->toBeNull();
    expect(DB::table('role_has_permissions')->where('role_id', $role->id->value)->count())->toBe(0);

    Event::assertDispatched(RoleDeleted::class, function (RoleDeleted $e): bool {
        // The name and grants must survive the row, or the audit entry is
        // unreadable once the role is gone.
        return $e->name === 'content_editor'
            && $e->permissions === ['content.publish', 'content.view'];
    });
});

test('a system role cannot be renamed or deleted through the use cases', function (): void {
    app(RolesSeeder::class)->run();
    $superAdmin = app(RoleRepositoryContract::class)->findByName('super_admin');

    expect(fn () => app(RenameRoleUseCase::class)->execute($superAdmin->id->value, 'root'))
        ->toThrow(SystemRoleImmutableException::class);

    expect(fn () => app(DeleteRoleUseCase::class)->execute($superAdmin->id->value))
        ->toThrow(SystemRoleImmutableException::class);

    expect(app(RoleRepositoryContract::class)->findByName('super_admin'))->not->toBeNull();
});

test('the repository round-trips a role with its permissions', function (): void {
    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view', 'content.create', 'content.publish']);

    $reloaded = app(RoleRepositoryContract::class)->findOrFail($role->id);

    expect($reloaded->getName())->toBe('content_editor');
    expect($reloaded->isSystem())->toBeFalse();
    expect($reloaded->getPermissionNames())->toBe(['content.create', 'content.publish', 'content.view']);
});

test('findOrFail throws for an unknown id', function (): void {
    app(RoleRepositoryContract::class)->findOrFail(RoleId::generate());
})->throws(ModelNotFoundException::class);

test('the repository refuses to grant a permission with no row', function (): void {
    // Catalogue and permissions table drifted. The use case catches an
    // unknown name before this point, so reaching the repository with one
    // means the seeder has not run — and silently dropping the grant
    // would leave a role claiming a capability the pivot never recorded.
    // Built by handing the aggregate a name whose row does not exist,
    // rather than by deleting a row out from under a saved role, which
    // just makes the reloaded role come back empty.
    DB::table('permissions')->where('name', 'content.view')->delete();

    $role = new Role(
        id: RoleId::generate(),
        name: 'content_editor',
        permissions: PermissionName::fromMany(['content.view']),
    );

    expect(fn () => app(RoleRepositoryContract::class)->save($role))
        ->toThrow(RuntimeException::class, 'content.view');
});

test('RolesSeeder creates the six actors with only super_admin protected', function (): void {
    app(RolesSeeder::class)->run();

    $roles = app(RoleRepositoryContract::class)->all();
    $names = array_map(static fn ($r): string => $r->getName(), $roles);

    expect($names)->toContain('super_admin', 'competition_manager', 'judge', 'evaluator', 'data_entry', 'moderator');
    expect($roles)->toHaveCount(6);

    foreach ($roles as $role) {
        expect($role->isSystem())->toBe($role->getName() === 'super_admin');
        expect($role->getPermissionNames())->not->toBeEmpty();
    }
});

test('super_admin holds every catalogue permission by enumeration', function (): void {
    app(RolesSeeder::class)->run();

    $superAdmin = app(RoleRepositoryContract::class)->findByName('super_admin');

    expect($superAdmin->getPermissionNames())->toHaveCount(PermissionCatalog::count());
    expect($superAdmin->getPermissionNames())->not->toContain('*');
});

test('competition_manager runs the competition but cannot touch identity', function (): void {
    // The separation the matrix exists to express: managing the
    // competition and controlling who may manage it are different jobs.
    app(RolesSeeder::class)->run();

    $names = app(RoleRepositoryContract::class)->findByName('competition_manager')->getPermissionNames();

    expect($names)->toContain('seasons.open_registration', 'stages.publish_results', 'applications.view');

    foreach ($names as $name) {
        expect(str_starts_with($name, 'users.'))->toBeFalse("competition_manager must not hold {$name}");
        expect(str_starts_with($name, 'roles.'))->toBeFalse("competition_manager must not hold {$name}");
        expect(str_starts_with($name, 'settings.'))->toBeFalse("competition_manager must not hold {$name}");
        expect($name)->not->toBe('audit.view');
    }
});

test('a newly added catalogue permission reaches super_admin on the next seed', function (): void {
    // The reason super_admin is seeded from PermissionCatalog::all()
    // rather than a hand-written list: the definition cannot go stale.
    app(RolesSeeder::class)->run();

    $superAdmin = app(RoleRepositoryContract::class)->findByName('super_admin');
    $reduced = array_slice($superAdmin->getPermissionNames(), 0, 5);

    // Simulate a catalogue that has grown since the role was written.
    DB::table('role_has_permissions')->where('role_id', $superAdmin->id->value)->delete();
    DB::table('role_has_permissions')->insert(
        DB::table('permissions')->whereIn('name', $reduced)->pluck('id')
            ->map(fn (string $pid): array => ['permission_id' => $pid, 'role_id' => $superAdmin->id->value])
            ->all()
    );

    expect(app(RoleRepositoryContract::class)->findByName('super_admin')->getPermissionNames())->toHaveCount(5);

    app(RolesSeeder::class)->run();

    expect(app(RoleRepositoryContract::class)->findByName('super_admin')->getPermissionNames())
        ->toHaveCount(PermissionCatalog::count());
});

test('re-seeding never reverts an operator edit to a non-system role', function (): void {
    app(RolesSeeder::class)->run();

    $moderator = app(RoleRepositoryContract::class)->findByName('moderator');
    app(SyncRolePermissionsUseCase::class)->execute($moderator->id->value, ['content.view']);

    app(RolesSeeder::class)->run();

    expect(app(RoleRepositoryContract::class)->all())->toHaveCount(6);
    expect(app(RoleRepositoryContract::class)->findByName('moderator')->getPermissionNames())->toBe(['content.view']);
});

/*
 * Epic 4 — Role ↔ Permission.
 */

test('SyncRolePermissionsUseCase replaces the set and dispatches the delta', function (): void {
    Event::fake();
    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view', 'content.create']);

    app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['content.view', 'content.publish'], roleUseCasesActor());

    expect(app(RoleRepositoryContract::class)->findOrFail($role->id)->getPermissionNames())
        ->toBe(['content.publish', 'content.view']);

    Event::assertDispatched(RolePermissionsChanged::class, function (RolePermissionsChanged $e): bool {
        return $e->added === ['content.publish'] && $e->removed === ['content.create'];
    });
});

test('SyncRolePermissionsUseCase can revoke everything', function (): void {
    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view']);

    app(SyncRolePermissionsUseCase::class)->execute($role->id->value, []);

    expect(app(RoleRepositoryContract::class)->findOrFail($role->id)->getPermissionNames())->toBeEmpty();
});

test('SyncRolePermissionsUseCase rejects a permission outside the catalogue and rolls back', function (): void {
    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view']);

    expect(fn () => app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['content.view', 'content.teleport']))
        ->toThrow(UnknownPermissionException::class);

    expect(app(RoleRepositoryContract::class)->findOrFail($role->id)->getPermissionNames())->toBe(['content.view']);
});

test('SyncRolePermissionsUseCase deduplicates a repeated name', function (): void {
    $role = app(CreateRoleUseCase::class)->execute('content_editor');

    app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['content.view', 'content.view']);

    expect(app(RoleRepositoryContract::class)->findOrFail($role->id)->getPermissionNames())->toBe(['content.view']);
});

test('SyncRolePermissionsUseCase records nothing when the set is unchanged', function (): void {
    Event::fake();
    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view']);

    app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['content.view']);

    Event::assertNotDispatched(RolePermissionsChanged::class);
});

test('no use case can change a system role, in either direction', function (): void {
    app(RolesSeeder::class)->run();
    $superAdmin = app(RoleRepositoryContract::class)->findByName('super_admin');
    $before = $superAdmin->getPermissionNames();

    // Revoking
    expect(fn () => app(SyncRolePermissionsUseCase::class)->execute($superAdmin->id->value, ['users.view']))
        ->toThrow(SystemRoleImmutableException::class);

    // Granting — the whole catalogue minus nothing is already held, so
    // build a set that would add something by removing one and adding it
    // back is impossible; instead assert the set is untouched after the
    // attempt above, which is the guarantee that matters.
    expect(app(RoleRepositoryContract::class)->findByName('super_admin')->getPermissionNames())->toBe($before);
});

test('a system role that would gain a permission is still refused', function (): void {
    // Constructed directly rather than through the seeder, so the role
    // genuinely lacks a permission the sync would add.
    $role = new Role(
        id: RoleId::generate(),
        name: 'restricted_system',
        isSystem: true,
        permissions: PermissionName::fromMany(['content.view']),
    );
    app(RoleRepositoryContract::class)->save($role);

    expect(fn () => app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['content.view', 'content.publish']))
        ->toThrow(SystemRoleImmutableException::class);

    expect(app(RoleRepositoryContract::class)->findOrFail($role->id)->getPermissionNames())->toBe(['content.view']);
});

test('every permission the catalogue defines can actually be granted', function (): void {
    // Guards the seam between the catalogue and the permissions table: if
    // the seeder missed an entry, granting it would fail here rather than
    // in production the first time someone builds a role around it.
    $role = app(CreateRoleUseCase::class)->execute('everything', PermissionCatalog::all());

    expect($role->getPermissionNames())->toHaveCount(PermissionCatalog::count());

    $reloaded = app(RoleRepositoryContract::class)->findOrFail($role->id);
    expect($reloaded->getPermissionNames())->toHaveCount(PermissionCatalog::count());
});
