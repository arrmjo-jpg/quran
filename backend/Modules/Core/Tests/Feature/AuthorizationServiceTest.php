<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\AssignRoleToUserUseCase;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Application\UseCases\SyncRolePermissionsUseCase;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\AuthorizationService;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'authorization');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function authzUser(string $email, string $type = UserType::ADMIN, bool $active = true): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Test',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => $active,
    ]);
}

function authzRoleId(string $name): string
{
    return app(RoleRepositoryContract::class)->findByName($name)->id->value;
}

/** An admin who holds real authority — see grantSuperAdmin() in Pest.php. */
function authzActor(string $email): UserModel
{
    $actor = authzUser($email);
    grantSuperAdmin((string) $actor->id);

    return $actor;
}

function authz(): AuthorizationService
{
    return app(AuthorizationService::class);
}

test('a user with no roles is allowed nothing', function (): void {
    $user = authzUser('nothing@quran.test');

    expect(authz()->allows($user, 'seasons.view'))->toBeFalse();
    expect(authz()->permissionsOf($user))->toBeEmpty();
});

test('a role grant is reflected in what is allowed', function (): void {
    $user = authzUser('granted@quran.test');
    $actor = authzActor('actor@quran.test');

    app(AssignRoleToUserUseCase::class)->execute((string) $user->id, authzRoleId('moderator'), (string) $actor->id);

    expect(authz()->allows($user, 'appeals.accept'))->toBeTrue();
    expect(authz()->allows($user, 'users.delete'))->toBeFalse();
});

test('allowsAll and allowsAny behave as their names claim', function (): void {
    $user = authzUser('combos@quran.test');
    $actor = authzActor('actor@quran.test');

    app(AssignRoleToUserUseCase::class)->execute((string) $user->id, authzRoleId('moderator'), (string) $actor->id);

    expect(authz()->allowsAll($user, ['appeals.accept', 'content.publish']))->toBeTrue();
    expect(authz()->allowsAll($user, ['appeals.accept', 'users.delete']))->toBeFalse();
    expect(authz()->allowsAny($user, ['users.delete', 'appeals.accept']))->toBeTrue();
    expect(authz()->allowsAny($user, ['users.delete', 'users.create']))->toBeFalse();
});

test('an unknown permission name is refused, not treated as ungoverned', function (): void {
    // Unknown-means-allowed would turn a typo in a Gate check into an
    // open door. Wrong direction for a mistake to fail in.
    $user = authzUser('unknown-perm@quran.test');
    $actor = authzActor('actor@quran.test');

    app(AssignRoleToUserUseCase::class)->execute((string) $user->id, authzRoleId('super_admin'), (string) $actor->id);

    expect(authz()->allows($user, 'seasons.teleport'))->toBeFalse();
    expect(authz()->allows($user, 'not_a_resource.not_an_action'))->toBeFalse();
});

test('a deactivated account is allowed nothing, whatever it holds', function (): void {
    // The roles are still attached and still correct; the account is not
    // permitted to act on them. This is why authorization is not a
    // synonym for resolution.
    $user = authzUser('deactivated@quran.test');
    $actor = authzActor('actor@quran.test');

    app(AssignRoleToUserUseCase::class)->execute((string) $user->id, authzRoleId('super_admin'), (string) $actor->id);
    expect(authz()->allows($user, 'seasons.view'))->toBeTrue();

    $user->update(['is_active' => false]);

    expect(authz()->allows($user->fresh(), 'seasons.view'))->toBeFalse();
    expect(authz()->permissionsOf($user->fresh()))->toBeEmpty();
});

test('a soft-deleted account is allowed nothing', function (): void {
    $user = authzUser('deleted@quran.test');
    $actor = authzActor('actor@quran.test');

    app(AssignRoleToUserUseCase::class)->execute((string) $user->id, authzRoleId('super_admin'), (string) $actor->id);
    $user->delete();

    $withTrashed = UserModel::withTrashed()->find($user->id);

    expect(authz()->allows($withTrashed, 'seasons.view'))->toBeFalse();
});

test('type is not consulted by authorization', function (): void {
    // Which surface an account may reach is the middleware's question
    // (ADR-015 §1). Answering it here too would put the door check in two
    // places that could disagree.
    $contestant = authzUser('contestant@quran.test', UserType::CONTESTANT);
    $actor = authzActor('actor@quran.test');

    app(AssignRoleToUserUseCase::class)->execute((string) $contestant->id, authzRoleId('moderator'), (string) $actor->id);

    expect(authz()->allows($contestant, 'appeals.accept'))->toBeTrue();
});

test('super_admin is allowed every catalogue permission', function (): void {
    $user = authzUser('super@quran.test');
    $actor = authzActor('actor@quran.test');

    app(AssignRoleToUserUseCase::class)->execute((string) $user->id, authzRoleId('super_admin'), (string) $actor->id);

    foreach (PermissionCatalog::all() as $permission) {
        expect(authz()->allows($user, $permission))->toBeTrue("super_admin should be allowed {$permission}");
    }
});

/*
|--------------------------------------------------------------------------
| Gate registration
|--------------------------------------------------------------------------
*/

test('every catalogue permission is registered as a Gate ability', function (): void {
    foreach (PermissionCatalog::all() as $permission) {
        expect(Gate::has($permission))->toBeTrue("No Gate ability registered for {$permission}");
    }
});

test('no Gate ability exists that the catalogue does not define', function (): void {
    // The other direction: an ability without a permission would be a
    // check nothing can ever satisfy.
    $abilities = array_keys(Gate::abilities());
    $unknown = array_filter(
        $abilities,
        static fn (string $ability): bool => str_contains($ability, '.') && ! PermissionCatalog::has($ability)
    );

    expect($unknown)->toBeEmpty('Gate abilities with no catalogue entry: '.implode(', ', $unknown));
});

test('Gate answers agree with the authorization service', function (): void {
    $user = authzUser('gate@quran.test');
    $actor = authzActor('actor@quran.test');

    app(AssignRoleToUserUseCase::class)->execute((string) $user->id, authzRoleId('moderator'), (string) $actor->id);

    expect(Gate::forUser($user)->allows('appeals.accept'))->toBeTrue();
    expect(Gate::forUser($user)->allows('users.delete'))->toBeFalse();
});

test('Gate reflects a role edit without any cache being touched by hand', function (): void {
    $user = authzUser('gate-live@quran.test');
    $actor = authzActor('actor@quran.test');

    $role = app(CreateRoleUseCase::class)->execute('content_editor', ['content.view']);
    app(AssignRoleToUserUseCase::class)->execute((string) $user->id, $role->id->value, (string) $actor->id);

    expect(Gate::forUser($user)->allows('content.publish'))->toBeFalse();

    app(SyncRolePermissionsUseCase::class)->execute($role->id->value, ['content.view', 'content.publish'], (string) $actor->id);

    expect(Gate::forUser($user)->allows('content.publish'))->toBeTrue();
});

test('there is no super-admin Gate bypass', function (): void {
    // A Gate::before bypass would mean the account that matters most is
    // never actually checked, hiding any resolution bug exactly where it
    // is most dangerous. super_admin passes because it holds every
    // permission by enumeration, not because it is special-cased.
    $superAdmin = authzUser('bypass@quran.test');
    $actor = authzActor('actor@quran.test');

    app(AssignRoleToUserUseCase::class)->execute((string) $superAdmin->id, authzRoleId('super_admin'), (string) $actor->id);

    // A name outside the catalogue must still be refused even for
    // super_admin — which could not be true if a bypass existed.
    expect(Gate::forUser($superAdmin)->allows('seasons.teleport'))->toBeFalse();
});

test('the admin API now depends on the abilities this service answers', function (): void {
    // The inverse of what this asserted through 6.2, and deliberately
    // inverted rather than deleted: it was the marker for "abilities are
    // registered but nothing consults them", and the same line is the
    // clearest place to record that the switch has been thrown.
    //
    // Route-by-route coverage lives in AdminRoutePermissionCoverageTest;
    // this only pins the fact that enforcement exists, next to the
    // service that makes the decision.
    $routeFile = (string) file_get_contents(base_path('Modules/Competition/Routes/admin.php'));

    expect(str_contains($routeFile, 'can:'))->toBeTrue();
});
