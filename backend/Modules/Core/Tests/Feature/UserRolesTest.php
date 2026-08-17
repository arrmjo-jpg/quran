<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\AssignRoleToUserUseCase;
use Modules\Core\Application\UseCases\DeleteRoleUseCase;
use Modules\Core\Application\UseCases\RevokeRoleFromUserUseCase;
use Modules\Core\Application\UseCases\SyncUserRolesUseCase;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Events\UserRolesChanged;
use Modules\Core\Domain\Exceptions\LastSystemRoleHolderException;
use Modules\Core\Domain\Exceptions\SelfRoleChangeException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'user-roles');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function makeAdmin(string $email): string
{
    $id = (string) Str::uuid();

    UserModel::query()->create([
        'id' => $id,
        'email' => $email,
        'name' => 'Admin',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    return $id;
}

function roleIdNamed(string $name): string
{
    return app(RoleRepositoryContract::class)->findByName($name)->id->value;
}

test('a user starts with no roles', function (): void {
    $userId = makeAdmin('no-roles@quran.test');

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($userId))->getRoleIdValues())->toBeEmpty();
});

test('AssignRoleToUserUseCase gives the user a role and dispatches the delta by name', function (): void {
    Event::fake();
    $userId = makeAdmin('assign@quran.test');
    $actor = makeAdmin('actor@quran.test');

    app(AssignRoleToUserUseCase::class)->execute($userId, roleIdNamed('moderator'), $actor);

    $user = app(UserRepositoryContract::class)->findOrFail(new UserId($userId));
    expect($user->getRoleIdValues())->toBe([roleIdNamed('moderator')]);

    Event::assertDispatched(UserRolesChanged::class, function (UserRolesChanged $e) use ($userId): bool {
        // Names, not ids — an audit entry reading "granted 0192…" is
        // unreadable a week later.
        return $e->userId === $userId && $e->added === ['moderator'] && $e->removed === [];
    });
});

test('assigning the same role twice changes nothing and records nothing', function (): void {
    Event::fake();
    $userId = makeAdmin('idempotent@quran.test');
    $actor = makeAdmin('actor@quran.test');
    $moderator = roleIdNamed('moderator');

    app(AssignRoleToUserUseCase::class)->execute($userId, $moderator, $actor);
    Event::fake();
    app(AssignRoleToUserUseCase::class)->execute($userId, $moderator, $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($userId))->getRoleIdValues())
        ->toBe([$moderator]);

    Event::assertNotDispatched(UserRolesChanged::class);
});

test('RevokeRoleFromUserUseCase removes just that role', function (): void {
    $userId = makeAdmin('revoke@quran.test');
    $actor = makeAdmin('actor@quran.test');

    app(SyncUserRolesUseCase::class)->execute($userId, [roleIdNamed('moderator'), roleIdNamed('judge')], $actor);
    app(RevokeRoleFromUserUseCase::class)->execute($userId, roleIdNamed('moderator'), $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($userId))->getRoleIdValues())
        ->toBe([roleIdNamed('judge')]);
});

test('SyncUserRolesUseCase replaces the whole set and reports both directions', function (): void {
    $userId = makeAdmin('sync@quran.test');
    $actor = makeAdmin('actor@quran.test');

    app(SyncUserRolesUseCase::class)->execute($userId, [roleIdNamed('moderator')], $actor);

    Event::fake();
    app(SyncUserRolesUseCase::class)->execute($userId, [roleIdNamed('judge'), roleIdNamed('evaluator')], $actor);

    $held = app(UserRepositoryContract::class)->findOrFail(new UserId($userId))->getRoleIdValues();
    expect($held)->toHaveCount(2);
    expect($held)->toContain(roleIdNamed('judge'), roleIdNamed('evaluator'));

    Event::assertDispatched(UserRolesChanged::class, function (UserRolesChanged $e): bool {
        $added = $e->added;
        sort($added);

        return $added === ['evaluator', 'judge'] && $e->removed === ['moderator'];
    });
});

test('syncing to an empty set removes every role', function (): void {
    $userId = makeAdmin('clear@quran.test');
    $actor = makeAdmin('actor@quran.test');

    app(SyncUserRolesUseCase::class)->execute($userId, [roleIdNamed('moderator')], $actor);
    app(SyncUserRolesUseCase::class)->execute($userId, [], $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($userId))->getRoleIdValues())->toBeEmpty();
    expect(DB::table('role_user')->where('user_id', $userId)->count())->toBe(0);
});

test('a duplicated role id in the request is deduplicated', function (): void {
    $userId = makeAdmin('dupes@quran.test');
    $actor = makeAdmin('actor@quran.test');
    $moderator = roleIdNamed('moderator');

    app(SyncUserRolesUseCase::class)->execute($userId, [$moderator, $moderator], $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($userId))->getRoleIdValues())->toBe([$moderator]);
});

test('assigning a role that does not exist is refused and rolls back', function (): void {
    $userId = makeAdmin('ghost-role@quran.test');
    $actor = makeAdmin('actor@quran.test');

    expect(fn () => app(SyncUserRolesUseCase::class)->execute($userId, [RoleId::generate()->value], $actor))
        ->toThrow(RuntimeException::class);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($userId))->getRoleIdValues())->toBeEmpty();
});

/*
 * PE-3 — you cannot change your own roles.
 */

test('a user cannot change their own roles', function (): void {
    $userId = makeAdmin('self@quran.test');

    expect(fn () => app(SyncUserRolesUseCase::class)->execute($userId, [roleIdNamed('moderator')], $userId))
        ->toThrow(SelfRoleChangeException::class);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($userId))->getRoleIdValues())->toBeEmpty();
});

test('PE-3 also blocks the assign and revoke conveniences', function (): void {
    // They delegate, so the guard must reach them — proving the
    // convenience is not a cheaper door into the pivot.
    $userId = makeAdmin('self2@quran.test');

    expect(fn () => app(AssignRoleToUserUseCase::class)->execute($userId, roleIdNamed('judge'), $userId))
        ->toThrow(SelfRoleChangeException::class);

    expect(fn () => app(RevokeRoleFromUserUseCase::class)->execute($userId, roleIdNamed('judge'), $userId))
        ->toThrow(SelfRoleChangeException::class);
});

/*
 * PE-5 — the last super_admin keeps the role.
 */

test('the last super_admin cannot lose the role', function (): void {
    $onlyAdmin = makeAdmin('only-super@quran.test');
    $actor = makeAdmin('actor@quran.test');
    $superAdmin = roleIdNamed('super_admin');

    app(AssignRoleToUserUseCase::class)->execute($onlyAdmin, $superAdmin, $actor);

    expect(fn () => app(RevokeRoleFromUserUseCase::class)->execute($onlyAdmin, $superAdmin, $actor))
        ->toThrow(LastSystemRoleHolderException::class);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($onlyAdmin))->getRoleIdValues())
        ->toBe([$superAdmin]);
});

test('a super_admin can lose the role while another active one remains', function (): void {
    $first = makeAdmin('super-1@quran.test');
    $second = makeAdmin('super-2@quran.test');
    $actor = makeAdmin('actor@quran.test');
    $superAdmin = roleIdNamed('super_admin');

    app(AssignRoleToUserUseCase::class)->execute($first, $superAdmin, $actor);
    app(AssignRoleToUserUseCase::class)->execute($second, $superAdmin, $actor);

    app(RevokeRoleFromUserUseCase::class)->execute($first, $superAdmin, $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($first))->getRoleIdValues())->toBeEmpty();
});

test('a deactivated super_admin does not satisfy PE-5', function (): void {
    // The guarantee is that someone can still administer the platform. An
    // account that cannot log in does not provide it, so it must not be
    // what keeps the check passing.
    $active = makeAdmin('super-active@quran.test');
    $deactivated = makeAdmin('super-inactive@quran.test');
    $actor = makeAdmin('actor@quran.test');
    $superAdmin = roleIdNamed('super_admin');

    app(AssignRoleToUserUseCase::class)->execute($active, $superAdmin, $actor);
    app(AssignRoleToUserUseCase::class)->execute($deactivated, $superAdmin, $actor);

    UserModel::query()->where('id', $deactivated)->update(['is_active' => false]);

    expect(fn () => app(RevokeRoleFromUserUseCase::class)->execute($active, $superAdmin, $actor))
        ->toThrow(LastSystemRoleHolderException::class);
});

test('removing an unrelated role never consults PE-5', function (): void {
    $userId = makeAdmin('unrelated@quran.test');
    $actor = makeAdmin('actor@quran.test');

    app(SyncUserRolesUseCase::class)->execute($userId, [roleIdNamed('judge')], $actor);
    app(RevokeRoleFromUserUseCase::class)->execute($userId, roleIdNamed('judge'), $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($userId))->getRoleIdValues())->toBeEmpty();
});

/*
 * Round-trip and boundary.
 */

test('the repository round-trips the roles a user holds', function (): void {
    $userId = makeAdmin('roundtrip@quran.test');
    $actor = makeAdmin('actor@quran.test');

    app(SyncUserRolesUseCase::class)->execute($userId, [roleIdNamed('judge'), roleIdNamed('moderator')], $actor);

    $reloaded = app(UserRepositoryContract::class)->findOrFail(new UserId($userId));

    expect($reloaded->getRoleIdValues())->toHaveCount(2);
    expect($reloaded->hasRole(new RoleId(roleIdNamed('judge'))))->toBeTrue();
    expect($reloaded->hasRole(new RoleId(roleIdNamed('evaluator'))))->toBeFalse();
});

test('deleting a role removes it from everyone holding it', function (): void {
    $userId = makeAdmin('cascade@quran.test');
    $actor = makeAdmin('actor@quran.test');
    $moderator = roleIdNamed('moderator');

    app(AssignRoleToUserUseCase::class)->execute($userId, $moderator, $actor);
    app(DeleteRoleUseCase::class)->execute($moderator, $actor);

    expect(app(UserRepositoryContract::class)->findOrFail(new UserId($userId))->getRoleIdValues())->toBeEmpty();
});

test('the User aggregate still cannot answer what permissions it has', function (): void {
    // The boundary this epic is scoped to. Turning roles into an
    // effective permission set is the enforcement epic's job, and an
    // aggregate that half-answered it would invite callers to depend on
    // the half.
    $methods = array_map(
        static fn (ReflectionMethod $m): string => strtolower($m->getName()),
        (new ReflectionClass(User::class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );

    foreach ($methods as $method) {
        expect(str_contains($method, 'permission'))
            ->toBeFalse("User::{$method}() suggests permission resolution has leaked into this epic.");
    }
});
