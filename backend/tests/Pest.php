<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature', 'Architecture', '../Modules');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Give an existing user super_admin, so they can act as a real actor.
 *
 * ADR-015 PE-1 reads the acting user's own effective permissions to
 * decide what they may grant, and EffectivePermissionResolver derives
 * those from roles and nothing else. An actor holding no roles can
 * therefore grant nothing — including the first role in a fresh
 * database — so any test that passes a `byUserId` needs its actor to
 * hold something first.
 *
 * Two shortcuts were tried and rejected, and the reasons are worth
 * keeping because both look reasonable:
 *
 *   - Passing `byUserId = null` exempts the call from PE-1 entirely.
 *     But a null actor means "the system did this", which is the
 *     seeder's privilege, not a test's, and it would leave the guard
 *     unexercised by anything resembling a real caller.
 *
 *   - Resolving `type='admin'` to the whole catalogue (which ADR-015
 *     originally specified for this transition) was implemented and
 *     reverted. It makes every admin omnipotent, which contradicts the
 *     resolver's own contract — "a user with no roles holds no
 *     permissions" — and makes PE-1 and PE-2 impossible to violate, so
 *     their tests could never fail. The ADR line was written before the
 *     resolver existed and did not survive contact with it.
 *
 * So the actor is granted a role the ordinary way: through the
 * aggregate and its repository. Deliberately NOT through
 * AssignRoleToUserUseCase — that use case is itself under test in
 * several of these files, and bootstrapping a fixture with the thing
 * being tested would let a bug in it masquerade as a passing suite.
 */
function grantSuperAdmin(string $userId): string
{
    $users = app(Modules\Core\Domain\Repositories\UserRepositoryContract::class);
    $roles = app(Modules\Core\Domain\Repositories\RoleRepositoryContract::class);

    $actor = $users->findOrFail(new Modules\Core\Domain\ValueObjects\UserId($userId));
    $actor->syncRoles([$roles->findByName('super_admin')->id]);
    $users->save($actor);

    return $userId;
}

/**
 * Take every role back off a user, by the same fixture route.
 *
 * Needed by the PE-5 tests, and the reason is a real interaction rather
 * than a workaround. PE-5 defends "the last active holder of a system
 * role keeps it", so those tests need exactly one super_admin — but
 * PE-1 means the actor who granted it had to hold super_admin too,
 * which makes two. Stripping the actor afterwards leaves the subject
 * genuinely last, which is the situation under test.
 *
 * The actor can still revoke afterwards while holding nothing: PE-1
 * checks only the roles being ADDED. Removing a role you do not hold is
 * not an escalation, and refusing it would strand an administrator who
 * needed to clean up after someone more privileged had left. That is a
 * decision recorded in SyncUserRolesUseCase, not an accident here.
 */
function revokeAllRoles(string $userId): string
{
    $users = app(Modules\Core\Domain\Repositories\UserRepositoryContract::class);

    $user = $users->findOrFail(new Modules\Core\Domain\ValueObjects\UserId($userId));
    $user->syncRoles([]);
    $users->save($user);

    app(Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver::class)
        ->forget(new Modules\Core\Domain\ValueObjects\UserId($userId));

    return $userId;
}
