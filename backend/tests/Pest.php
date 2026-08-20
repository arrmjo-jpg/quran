<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;
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
    $users = app(UserRepositoryContract::class);
    $roles = app(RoleRepositoryContract::class);

    // Self-sufficient on purpose. Most test files never seeded roles —
    // they had no reason to before authorization existed — and requiring
    // each of them to remember a beforeEach would make this fixture fail
    // in a way that looks like an authorization bug rather than a missing
    // seed. Both seeders are safe to re-run: PermissionsSeeder inserts
    // only what is missing, and RolesSeeder re-synchronises system roles
    // while leaving the five editable ones untouched.
    if ($roles->findByName('super_admin') === null) {
        (new PermissionsSeeder)->run();
        app(RolesSeeder::class)->run();
    }

    $actor = $users->findOrFail(new UserId($userId));
    $actor->syncRoles([$roles->findByName('super_admin')->id]);
    $users->save($actor);

    app(EffectivePermissionResolver::class)
        ->forget(new UserId($userId));

    return $userId;
}

/**
 * Wrap an admin the moment it is created, so it holds super_admin.
 *
 * TRANSITIONAL — ADR-015's activation epic. Until authorization is
 * switched on, every admin in these tests could do everything by virtue
 * of `type='admin'` alone. Once Gate checks guard the admin routes, an
 * admin holding no roles can do nothing, and ~290 actingAs() calls
 * across 36 files would start failing for a reason that has nothing to
 * do with what they are testing. Granting super_admin keeps them
 * meaning exactly what they meant before.
 *
 * Applied at each creation site rather than by a global model observer,
 * deliberately. An observer would be five lines instead of thirty-five
 * edits, but it would also make "an admin who is NOT allowed to do this"
 * impossible to express — and the activation epic has eleven existing
 * 403 assertions to re-examine, plus new denial tests to write. A
 * fixture that cannot represent a refusal is the same mistake as a
 * resolver that cannot represent one.
 *
 * Returns the model so it can be wrapped around a create() call without
 * restructuring the surrounding statement.
 */
function withSuperAdmin(UserModel $user): UserModel
{
    // A no-op for anyone who is not an admin, which is what lets this
    // wrap the helpers that take their type as a parameter — several
    // files use one factory for both admins and contestants, defaulting
    // to admin. Deciding here rather than at each call site means a
    // contestant fixture can never be handed super_admin by a wrap that
    // looked right at the time.
    if ($user->type !== UserType::ADMIN) {
        return $user;
    }

    grantSuperAdmin((string) $user->id);

    return $user;
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
    $users = app(UserRepositoryContract::class);

    $user = $users->findOrFail(new UserId($userId));
    $user->syncRoles([]);
    $users->save($user);

    app(EffectivePermissionResolver::class)
        ->forget(new UserId($userId));

    return $userId;
}

/**
 * Enrols a contestant in a freshly made circle, and returns the circle id.
 *
 * ADR-016 Q4 made an active membership a precondition for submitting an
 * application (G3). Tests that submit therefore need their contestant to
 * belong somewhere — this is the one line that makes them eligible, and it
 * exists here rather than in four copies because four copies is how three of
 * them end up subtly different.
 *
 * Raw inserts rather than Organization's models: the callers live in
 * tests/Feature and several belong to other modules, and the ids are all they
 * need.
 */
function enrolInCircle(string $contestantId): string
{
    $countryId = DB::table('countries')->value('id');

    if ($countryId === null) {
        $countryId = (string) Str::uuid();

        DB::table('countries')->insert([
            'id' => $countryId,
            'iso_code' => 'JO',
            'iso3_code' => 'JOR',
            'phone_code' => '+962',
            'flag_url' => 'https://example.test/flag.svg',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $centerId = (string) Str::uuid();

    DB::table('centers')->insert([
        'id' => $centerId,
        'name' => 'Test Centre '.Str::random(6),
        'country_id' => (string) $countryId,
        'city' => 'Amman',
        'address' => '1 Test Street',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $circleId = (string) Str::uuid();

    DB::table('circles')->insert([
        'id' => $circleId,
        'center_id' => $centerId,
        'name' => 'Test Circle '.Str::random(6),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('contestant_memberships')->insert([
        'id' => (string) Str::uuid(),
        'contestant_id' => $contestantId,
        'circle_id' => $circleId,
        'joined_at' => now()->subMonths(6),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $circleId;
}
