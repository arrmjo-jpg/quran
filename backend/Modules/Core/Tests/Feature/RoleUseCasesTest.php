<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Application\UseCases\DeleteRoleUseCase;
use Modules\Core\Application\UseCases\RenameRoleUseCase;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Events\RoleCreated;
use Modules\Core\Domain\Events\RoleDeleted;
use Modules\Core\Domain\Events\RoleRenamed;
use Modules\Core\Domain\Exceptions\SystemRoleImmutableException;
use Modules\Core\Domain\Exceptions\UnknownPermissionException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\PermissionName;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'roles');

beforeEach(function (): void {
    // Roles can only be granted permissions that exist as rows, so the
    // catalogue has to be seeded before any of this is meaningful.
    (new PermissionsSeeder)->run();
});

test('CreateRoleUseCase persists a custom role and dispatches RoleCreated', function (): void {
    Event::fake();

    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view', 'content.publish'], 'admin-1');

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

    $renamed = app(RenameRoleUseCase::class)->execute($role->id->value, 'cms_editor', 'admin-1');

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

    app(DeleteRoleUseCase::class)->execute($role->id->value, 'admin-1');

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
        // Permissions attach in the Role-Permission epic, not here.
        expect($role->getPermissionNames())->toBeEmpty();
    }
});

test('RolesSeeder is idempotent and never overwrites an operator edit', function (): void {
    app(RolesSeeder::class)->run();

    $moderator = app(RoleRepositoryContract::class)->findByName('moderator');
    $moderator->syncPermissions(PermissionName::fromMany(['content.view']));
    app(RoleRepositoryContract::class)->save($moderator);

    app(RolesSeeder::class)->run();

    expect(app(RoleRepositoryContract::class)->all())->toHaveCount(6);
    expect(app(RoleRepositoryContract::class)->findByName('moderator')->getPermissionNames())->toBe(['content.view']);
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
