<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\AssignRoleToUserUseCase;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Application\UseCases\DeleteRoleUseCase;
use Modules\Core\Application\UseCases\SyncRolePermissionsUseCase;
use Modules\Core\Application\UseCases\SyncUserRolesUseCase;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'permissions', 'resolver');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function resolverUser(string $email): string
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

function resolverRoleId(string $name): string
{
    return app(RoleRepositoryContract::class)->findByName($name)->id->value;
}

function resolver(): EffectivePermissionResolver
{
    return app(EffectivePermissionResolver::class);
}

test('a user with no roles holds no permissions', function (): void {
    $userId = resolverUser('none@quran.test');

    expect(resolver()->forUser(new UserId($userId)))->toBeEmpty();
});

test('permissions resolve through the roles a user holds', function (): void {
    $userId = resolverUser('judge@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    app(AssignRoleToUserUseCase::class)->execute($userId, resolverRoleId('judge'), $actor);

    $held = resolver()->forUser(new UserId($userId));

    expect($held)->toContain('evaluations.submit', 'applications.view');
    expect($held)->not->toContain('users.create');
});

test('holding two roles yields the union, without duplicates', function (): void {
    $userId = resolverUser('two-roles@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    // judge and evaluator overlap on evaluations.* and media.view.
    app(SyncUserRolesUseCase::class)->execute(
        $userId,
        [resolverRoleId('judge'), resolverRoleId('evaluator')],
        $actor
    );

    $held = resolver()->forUser(new UserId($userId));

    expect($held)->toBe(array_values(array_unique($held)));
    expect($held)->toContain('evaluations.submit', 'judges.view');
});

test('has() and hasAll() answer from the resolved set', function (): void {
    $userId = resolverUser('checks@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    app(AssignRoleToUserUseCase::class)->execute($userId, resolverRoleId('moderator'), $actor);

    expect(resolver()->has(new UserId($userId), 'appeals.accept'))->toBeTrue();
    expect(resolver()->has(new UserId($userId), 'users.delete'))->toBeFalse();
    expect(resolver()->hasAll(new UserId($userId), ['appeals.accept', 'content.publish']))->toBeTrue();
    expect(resolver()->hasAll(new UserId($userId), ['appeals.accept', 'users.delete']))->toBeFalse();
});

test('super_admin resolves to the entire catalogue', function (): void {
    $userId = resolverUser('super@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    app(AssignRoleToUserUseCase::class)->execute($userId, resolverRoleId('super_admin'), $actor);

    expect(resolver()->forUser(new UserId($userId)))
        ->toHaveCount(PermissionCatalog::count());
});

test('the result is cached rather than re-queried', function (): void {
    $userId = resolverUser('cached@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    app(AssignRoleToUserUseCase::class)->execute($userId, resolverRoleId('judge'), $actor);

    resolver()->forUser(new UserId($userId));

    expect(Cache::has("rbac:user:{$userId}:permissions"))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Invalidation — the part where a mistake is a security bug
|--------------------------------------------------------------------------
| ADR-015 §4.6 names two regression tests specifically. Both are here,
| plus one per writer.
*/

test('WRITER 1 — changing a user\'s roles is visible on the next read', function (): void {
    $userId = resolverUser('writer1@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    app(AssignRoleToUserUseCase::class)->execute($userId, resolverRoleId('judge'), $actor);
    expect(resolver()->has(new UserId($userId), 'evaluations.submit'))->toBeTrue();

    app(SyncUserRolesUseCase::class)->execute($userId, [], $actor);

    expect(resolver()->has(new UserId($userId), 'evaluations.submit'))->toBeFalse();
    expect(resolver()->forUser(new UserId($userId)))->toBeEmpty();
});

test('WRITER 2 — a role edit is visible to an affected user on their next read', function (): void {
    // ADR-015 §4.6's first named regression test: the stale-authorization
    // failure this design is most exposed to.
    $userId = resolverUser('writer2@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view']);
    app(AssignRoleToUserUseCase::class)->execute($userId, $role->id->value, $actor);

    // Warm the cache with the old set.
    expect(resolver()->has(new UserId($userId), 'content.publish'))->toBeFalse();

    app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['content.view', 'content.publish'], $actor);

    expect(resolver()->has(new UserId($userId), 'content.publish'))->toBeTrue();
});

test('WRITER 2 — revoking from a role removes it from holders immediately', function (): void {
    $userId = resolverUser('writer2b@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view', 'content.publish']);
    app(AssignRoleToUserUseCase::class)->execute($userId, $role->id->value, $actor);

    expect(resolver()->has(new UserId($userId), 'content.publish'))->toBeTrue();

    app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['content.view'], $actor);

    expect(resolver()->has(new UserId($userId), 'content.publish'))->toBeFalse();
});

test('WRITER 3 — deleting a role clears the cache of everyone who held it', function (): void {
    // ADR-015 §4.6's second named regression test. The ordering is the
    // whole point: holders are captured before the pivot rows go, because
    // asking afterwards finds nobody.
    $first = resolverUser('writer3a@quran.test');
    $second = resolverUser('writer3b@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view', 'content.publish']);
    app(AssignRoleToUserUseCase::class)->execute($first, $role->id->value, $actor);
    app(AssignRoleToUserUseCase::class)->execute($second, $role->id->value, $actor);

    // Warm both caches so a missed invalidation would actually show.
    expect(resolver()->has(new UserId($first), 'content.publish'))->toBeTrue();
    expect(resolver()->has(new UserId($second), 'content.publish'))->toBeTrue();

    app(DeleteRoleUseCase::class)->execute($role->id->value, $actor);

    expect(resolver()->forUser(new UserId($first)))->toBeEmpty();
    expect(resolver()->forUser(new UserId($second)))->toBeEmpty();
});

test('holdersOf finds every holder, and finds none once the role is gone', function (): void {
    $userId = resolverUser('holders@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view']);
    app(AssignRoleToUserUseCase::class)->execute($userId, $role->id->value, $actor);

    expect(resolver()->holdersOf($role->id))->toBe([$userId]);

    app(DeleteRoleUseCase::class)->execute($role->id->value, $actor);

    // Proves why the delete path must capture holders first.
    expect(resolver()->holdersOf($role->id))->toBeEmpty();
});

test('one user\'s invalidation does not disturb another\'s cache', function (): void {
    $changed = resolverUser('isolated-a@quran.test');
    $untouched = resolverUser('isolated-b@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    app(AssignRoleToUserUseCase::class)->execute($changed, resolverRoleId('judge'), $actor);
    app(AssignRoleToUserUseCase::class)->execute($untouched, resolverRoleId('moderator'), $actor);

    resolver()->forUser(new UserId($changed));
    resolver()->forUser(new UserId($untouched));

    app(SyncUserRolesUseCase::class)->execute($changed, [], $actor);

    // Cache::flush() would have taken this one too — which is why the
    // resolver never calls it.
    expect(Cache::has("rbac:user:{$untouched}:permissions"))->toBeTrue();
    expect(Cache::has("rbac:user:{$changed}:permissions"))->toBeFalse();
});

test('the resolver never calls Cache::flush', function (): void {
    // Flushing would drop every unrelated cache the platform keeps, and
    // would hide a missing invalidation by making everything appear to
    // work.
    //
    // Comments are stripped before checking, via PHP's own tokeniser:
    // the class docblock says "Cache::flush() is never called here",
    // which is documentation of the rule rather than a breach of it.
    $source = (string) file_get_contents(
        base_path('Modules/Core/Infrastructure/Permissions/EffectivePermissionResolver.php')
    );

    $code = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $code .= is_array($token) ? $token[1] : $token;
    }

    expect(str_contains($code, 'flush'))->toBeFalse('The resolver must never flush the cache.');
});

test('a role with no permissions contributes nothing', function (): void {
    $userId = resolverUser('empty-role@quran.test');
    $actor = grantSuperAdmin(resolverUser('actor@quran.test'));

    $role = app(CreateRoleUseCase::class)->execute('empty_role');
    app(AssignRoleToUserUseCase::class)->execute($userId, $role->id->value, $actor);

    expect(resolver()->forUser(new UserId($userId)))->toBeEmpty();
});

test('forgetHoldersOf on a role nobody holds is harmless', function (): void {
    $role = app(CreateRoleUseCase::class)->execute('unheld_role', ['content.view']);

    resolver()->forgetHoldersOf($role->id);

    expect(resolver()->holdersOf($role->id))->toBeEmpty();
});

test('forget on an unknown user is harmless', function (): void {
    resolver()->forget(new UserId((string) Str::uuid()));
})->throwsNoExceptions();

test('holdersOf on an unknown role returns nothing', function (): void {
    expect(resolver()->holdersOf(RoleId::generate()))->toBeEmpty();
});
