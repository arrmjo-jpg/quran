<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\AssignRoleToUserUseCase;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Application\UseCases\RevokeRoleFromUserUseCase;
use Modules\Core\Application\UseCases\SyncRolePermissionsUseCase;
use Modules\Core\Application\UseCases\SyncUserRolesUseCase;
use Modules\Core\Domain\Exceptions\PrivilegeEscalationException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'privilege-escalation');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function peUser(string $email): string
{
    $id = (string) Str::uuid();

    UserModel::query()->create([
        'id' => $id,
        'email' => $email,
        'name' => 'Test',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    return $id;
}

function peRoleId(string $name): string
{
    return app(RoleRepositoryContract::class)->findByName($name)->id->value;
}

/** Gives a user a role without going through PE-1, as a seeder would. */
function peGrantDirectly(string $userId, string $roleName): void
{
    app(SyncUserRolesUseCase::class)->execute($userId, [peRoleId($roleName)], null);
}

/*
|--------------------------------------------------------------------------
| PE-1 — you cannot grant a role carrying permissions you do not hold
|--------------------------------------------------------------------------
*/

test('an actor cannot grant a role that exceeds their own permissions', function (): void {
    $actor = peUser('weak-actor@quran.test');
    $target = peUser('target@quran.test');

    // moderator holds appeals.* and content.*; judge holds none of them.
    peGrantDirectly($actor, 'judge');

    expect(fn () => app(AssignRoleToUserUseCase::class)->execute($target, peRoleId('moderator'), $actor))
        ->toThrow(PrivilegeEscalationException::class);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($target))->getRoleIdValues())->toBeEmpty();
});

test('an actor can grant a role whose permissions they hold entirely', function (): void {
    $actor = peUser('super-actor@quran.test');
    $target = peUser('target2@quran.test');

    peGrantDirectly($actor, 'super_admin');

    app(AssignRoleToUserUseCase::class)->execute($target, peRoleId('moderator'), $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($target))->getRoleIdValues())
        ->toBe([peRoleId('moderator')]);
});

test('an actor can grant a role identical to one they hold', function (): void {
    $actor = peUser('peer-actor@quran.test');
    $target = peUser('target3@quran.test');

    peGrantDirectly($actor, 'moderator');

    app(AssignRoleToUserUseCase::class)->execute($target, peRoleId('moderator'), $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($target))->getRoleIdValues())
        ->toBe([peRoleId('moderator')]);
});

test('an actor with no roles can grant nothing', function (): void {
    $actor = peUser('roleless-actor@quran.test');
    $target = peUser('target4@quran.test');

    expect(fn () => app(AssignRoleToUserUseCase::class)->execute($target, peRoleId('judge'), $actor))
        ->toThrow(PrivilegeEscalationException::class);
});

test('the refusal names the permissions that exceeded the actor', function (): void {
    // A bare "forbidden" would send an administrator guessing.
    $actor = peUser('named@quran.test');
    $target = peUser('target5@quran.test');

    peGrantDirectly($actor, 'judge');

    try {
        app(AssignRoleToUserUseCase::class)->execute($target, peRoleId('moderator'), $actor);
        expect(false)->toBeTrue('Expected a PrivilegeEscalationException.');
    } catch (PrivilegeEscalationException $e) {
        expect($e->subject)->toBe('moderator');
        expect($e->exceeding)->toContain('appeals.accept');
    }
});

test('REVOKING a role the actor does not hold is allowed', function (): void {
    // Removing capability is not escalation. Refusing it would stop an
    // administrator from cleaning up after someone more privileged left.
    $actor = peUser('cleaner@quran.test');
    $target = peUser('target6@quran.test');

    peGrantDirectly($target, 'moderator');
    peGrantDirectly($actor, 'judge');

    app(RevokeRoleFromUserUseCase::class)->execute($target, peRoleId('moderator'), $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($target))->getRoleIdValues())->toBeEmpty();
});

test('a system action with no actor is exempt from PE-1', function (): void {
    // Seeders and console commands run from already-trusted code; the
    // alternative would be making the seeder impersonate someone.
    $target = peUser('system-target@quran.test');

    app(SyncUserRolesUseCase::class)->execute($target, [peRoleId('super_admin')], null);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($target))->getRoleIdValues())
        ->toBe([peRoleId('super_admin')]);
});

test('PE-1 uses permissions, never role names or any ranking', function (): void {
    // A custom role holding a strict subset of the actor's permissions is
    // grantable even though it is unrelated to any role the actor holds —
    // which could not be true if the check compared role names or seniority.
    $actor = peUser('subset-actor@quran.test');
    $target = peUser('target7@quran.test');

    peGrantDirectly($actor, 'moderator');

    $narrow = app(CreateRoleUseCase::class)->execute('appeal_reader', ['appeals.view'], null);

    app(AssignRoleToUserUseCase::class)->execute($target, $narrow->id->value, $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($target))->getRoleIdValues())
        ->toBe([$narrow->id->value]);
});

/*
|--------------------------------------------------------------------------
| PE-2 — you cannot edit a role into something you could not grant
|--------------------------------------------------------------------------
*/

test('an actor cannot add a permission they do not hold to a role', function (): void {
    // Without PE-2, PE-1 is trivially bypassed: take a role you may
    // grant, edit it into anything, then grant it.
    $actor = peUser('editor@quran.test');
    peGrantDirectly($actor, 'judge');

    $role = app(CreateRoleUseCase::class)->execute('small_role', ['evaluations.view'], null);

    expect(fn () => app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['evaluations.view', 'users.delete'], $actor))
        ->toThrow(PrivilegeEscalationException::class);

    expect(app(RoleRepositoryContract::class)->findOrFail($role->id)->getPermissionNames())
        ->toBe(['evaluations.view']);
});

test('an actor can add permissions they hold', function (): void {
    $actor = peUser('editor2@quran.test');
    peGrantDirectly($actor, 'moderator');

    $role = app(CreateRoleUseCase::class)->execute('small_role', ['appeals.view'], null);

    app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['appeals.view', 'appeals.accept'], $actor);

    expect(app(RoleRepositoryContract::class)->findOrFail($role->id)->getPermissionNames())
        ->toBe(['appeals.accept', 'appeals.view']);
});

test('REMOVING a permission the actor does not hold is allowed', function (): void {
    // Trimming an inherited role is not escalation.
    $actor = peUser('trimmer@quran.test');
    peGrantDirectly($actor, 'judge');

    $role = app(CreateRoleUseCase::class)->execute('fat_role', ['evaluations.view', 'users.delete'], null);

    app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['evaluations.view'], $actor);

    expect(app(RoleRepositoryContract::class)->findOrFail($role->id)->getPermissionNames())
        ->toBe(['evaluations.view']);
});

test('a system action with no actor is exempt from PE-2', function (): void {
    $role = app(CreateRoleUseCase::class)->execute('seeded_role', [], null);

    app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['users.delete'], null);

    expect(app(RoleRepositoryContract::class)->findOrFail($role->id)->getPermissionNames())->toBe(['users.delete']);
});

test('the escalation path PE-2 exists to close is actually closed', function (): void {
    // The full attack, end to end: an actor grants themselves nothing
    // they could not already grant, then tries to inflate a grantable
    // role and hand it over.
    $actor = peUser('attacker@quran.test');
    $victimTarget = peUser('accomplice@quran.test');

    peGrantDirectly($actor, 'judge');

    $trojan = app(CreateRoleUseCase::class)->execute('trojan', ['evaluations.view'], null);

    // Step 1: inflate the role — refused by PE-2.
    expect(fn () => app(SyncRolePermissionsUseCase::class)->execute($trojan->id->value, ['evaluations.view', 'users.create'], $actor))
        ->toThrow(PrivilegeEscalationException::class);

    // Step 2: so the role stays harmless, and granting it is fine.
    app(AssignRoleToUserUseCase::class)->execute($victimTarget, $trojan->id->value, $actor);

    expect(app(\Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver::class)
        ->forUser(new UserId($victimTarget)))
        ->toBe(['evaluations.view']);
});
