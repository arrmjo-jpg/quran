<?php

declare(strict_types=1);

use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Events\RoleCreated;
use Modules\Core\Domain\Events\RolePermissionsChanged;
use Modules\Core\Domain\Events\RoleRenamed;
use Modules\Core\Domain\Exceptions\SystemRoleImmutableException;
use Modules\Core\Domain\ValueObjects\PermissionName;
use Modules\Core\Domain\ValueObjects\RoleId;

uses()->group('core', 'unit', 'domain', 'identity');

test('creating a role records RoleCreated', function (): void {
    $role = Role::create(RoleId::generate(), 'content_editor', byUserId: 'admin-1');

    expect($role->getName())->toBe('content_editor');
    expect($role->isSystem())->toBeFalse();
    expect($role->getPermissions())->toBeEmpty();

    $events = $role->releaseEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(RoleCreated::class);
    expect($events[0]->byUserId)->toBe('admin-1');
});

test('role names must be snake_case', function (string $invalid): void {
    Role::create(RoleId::generate(), $invalid);
})->throws(InvalidArgumentException::class)->with([
    'Content Editor',
    'contentEditor',
    'content-editor',
    'Content_Editor',
    '_leading',
    'trailing_',
    '1numeric_start',
]);

test('a role name cannot be empty', function (): void {
    Role::create(RoleId::generate(), '   ');
})->throws(InvalidArgumentException::class);

test('syncPermissions records only the delta', function (): void {
    $role = new Role(RoleId::generate(), 'content_editor', permissions: PermissionName::fromMany([
        'content.view', 'content.create',
    ]));

    $role->syncPermissions(PermissionName::fromMany(['content.view', 'content.publish']));

    $events = $role->releaseEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(RolePermissionsChanged::class);
    expect($events[0]->added)->toBe(['content.publish']);
    expect($events[0]->removed)->toBe(['content.create']);
    expect($role->getPermissionNames())->toBe(['content.view', 'content.publish']);
});

test('syncPermissions with no actual change records nothing', function (): void {
    $role = new Role(RoleId::generate(), 'content_editor', permissions: PermissionName::fromMany(['content.view']));

    $role->syncPermissions(PermissionName::fromMany(['content.view']));

    expect($role->releaseEvents())->toBeEmpty();
});

test('granting the same permission twice is idempotent', function (): void {
    $role = new Role(RoleId::generate(), 'content_editor');

    $role->syncPermissions(PermissionName::fromMany(['content.view', 'content.view']));

    expect($role->getPermissionNames())->toBe(['content.view']);
});

test('renaming to the same name records nothing', function (): void {
    $role = new Role(RoleId::generate(), 'content_editor');

    $role->rename('content_editor');

    expect($role->releaseEvents())->toBeEmpty();
});

test('renaming records RoleRenamed with both names', function (): void {
    $role = new Role(RoleId::generate(), 'content_editor');

    $role->rename('cms_editor', 'admin-1');

    $events = $role->releaseEvents();
    expect($events[0])->toBeInstanceOf(RoleRenamed::class);
    expect($events[0]->oldName)->toBe('content_editor');
    expect($events[0]->newName)->toBe('cms_editor');
});

/*
 * PE-4 — a system role is protected by its column, and the protection is
 * enforced in the aggregate rather than by the caller remembering to ask.
 */

test('a system role cannot be renamed', function (): void {
    $role = new Role(RoleId::generate(), 'super_admin', isSystem: true);

    $role->rename('root');
})->throws(SystemRoleImmutableException::class);

test('a system role cannot be deleted', function (): void {
    $role = new Role(RoleId::generate(), 'super_admin', isSystem: true);

    $role->assertDeletable();
})->throws(SystemRoleImmutableException::class);

test('a system role cannot have permissions revoked', function (): void {
    $role = new Role(RoleId::generate(), 'super_admin', isSystem: true, permissions: PermissionName::fromMany([
        'users.view', 'users.create',
    ]));

    $role->syncPermissions(PermissionName::fromMany(['users.view']));
})->throws(SystemRoleImmutableException::class);

test('a system role may still gain permissions', function (): void {
    // Additive change is safe: PE-4 protects against losing capability,
    // and a new catalogue entry must be grantable to super_admin or it
    // could never hold every permission.
    $role = new Role(RoleId::generate(), 'super_admin', isSystem: true, permissions: PermissionName::fromMany([
        'users.view',
    ]));

    $role->syncPermissions(PermissionName::fromMany(['users.view', 'users.create']));

    expect($role->getPermissionNames())->toBe(['users.view', 'users.create']);
    expect($role->releaseEvents()[0]->added)->toBe(['users.create']);
});

test('a custom role can be renamed, deleted and stripped', function (): void {
    $role = new Role(RoleId::generate(), 'content_editor', permissions: PermissionName::fromMany(['content.view']));

    $role->rename('cms_editor');
    $role->syncPermissions([]);
    $role->assertDeletable();

    expect($role->getName())->toBe('cms_editor');
    expect($role->getPermissionNames())->toBeEmpty();
});

test('there is no way to change is_system after construction', function (): void {
    // PE-4 depends on the flag being seeder-only. A promote/demote path
    // would let the guard be bypassed through the UI it protects.
    $methods = array_map(
        static fn (ReflectionMethod $m): string => $m->getName(),
        (new ReflectionClass(Role::class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );

    foreach ($methods as $method) {
        expect(str_contains(strtolower($method), 'system') && $method !== 'isSystem')
            ->toBeFalse("Role::{$method}() looks like it can change is_system.");
    }
});
