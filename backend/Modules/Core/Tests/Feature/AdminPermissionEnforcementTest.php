<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'authorization', 'enforcement');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

/*
|--------------------------------------------------------------------------
| Enforcement — the proof that the switch is actually on
|--------------------------------------------------------------------------
|
| Every other admin test in this suite acts as a super_admin, because
| that is what those tests need in order to test what they are about.
| That makes them useless as evidence here: they would stay green if the
| `can:` middleware were deleted tomorrow.
|
| These tests use roles that hold *some* of the catalogue, so a refusal
| is possible and a success means something. Without them, "the suite is
| green" after enforcement would only mean super_admin can do everything,
| which was already true.
|
| Note what is deliberately NOT asserted: that a contestant is refused.
| That is EnsureUserIsAdmin's job, it was true before this epic, and
| AdminAuthorizationTest already covers it. Mixing the two would make it
| unclear which mechanism a green test is exercising.
*/

function enforcementUser(string $email, string ...$roleNames): UserModel
{
    $user = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Enforcement Test',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    if ($roleNames !== []) {
        $roles = app(RoleRepositoryContract::class);
        $users = app(UserRepositoryContract::class);

        $entity = $users->findOrFail(new UserId((string) $user->id));
        $entity->syncRoles(array_map(
            static fn (string $n) => $roles->findByName($n)->id,
            $roleNames
        ));
        $users->save($entity);
    }

    return $user;
}

test('an admin with no roles is refused an admin route', function (): void {
    // The switch itself. Before this epic type='admin' was sufficient;
    // now it only opens the door.
    $admin = enforcementUser('roleless@quran.test');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/seasons')
        ->assertStatus(403);
});

test('competition_manager reaches the competition surface', function (): void {
    $manager = enforcementUser('manager@quran.test', 'competition_manager');

    $this->actingAs($manager)->getJson('/api/v1/admin/seasons')->assertStatus(200);
    $this->actingAs($manager)->getJson('/api/v1/admin/judges')->assertStatus(200);
    $this->actingAs($manager)->getJson('/api/v1/admin/participation-types')->assertStatus(200);
});

test('competition_manager cannot touch identity', function (): void {
    // ADR-015 §7.3's central separation: running the competition and
    // controlling who may run it are different jobs. If this ever goes
    // green by holding the permission rather than by the route being
    // absent, the matrix has drifted.
    $manager = enforcementUser('manager-identity@quran.test', 'competition_manager');
    $held = app(RoleRepositoryContract::class)->findByName('competition_manager')->getPermissionNames();

    // ONE NEEDLE PER ASSERTION, DELIBERATELY. Pest's toContain is variadic
    // and `not` negates the whole conjunction, so
    // `->not->toContain('a', 'b')` asserts only "does not hold BOTH" — a role
    // holding 'a' and not 'b' would sail through. Proven with a throwaway
    // test: a role holding one of four forbidden permissions passed the
    // four-argument form. Separated so each one can actually fail.
    foreach (['users.view', 'users.create', 'roles.view', 'roles.update', 'audit.view', 'settings.update'] as $forbidden) {
        expect($held)->not->toContain($forbidden);
    }
});

test('a role that can view seasons still cannot change them', function (): void {
    // The finest-grained claim in the whole design: read and write are
    // separately grantable on the same resource.
    $roles = app(RoleRepositoryContract::class);
    $viewer = app(CreateRoleUseCase::class)
        ->execute('season_viewer', ['seasons.view']);

    $user = enforcementUser('viewer@quran.test', 'season_viewer');

    $this->actingAs($user)->getJson('/api/v1/admin/seasons')->assertStatus(200);

    $this->actingAs($user)
        ->postJson('/api/v1/admin/seasons', ['slug' => 'nope', 'year' => 2040])
        ->assertStatus(403);

    expect($roles->findByName('season_viewer'))->not->toBeNull();
    expect($viewer->getPermissionNames())->toBe(['seasons.view']);
});

test('starting a broadcast is refused to a role that may only create one', function (): void {
    // The reason streaming.start exists as its own permission. If this
    // test ever passes because the route checks streaming.create, the
    // separation added in 6.3c-0 has been quietly undone.
    app(CreateRoleUseCase::class)
        ->execute('stream_setup', ['streaming.view', 'streaming.create']);

    $user = enforcementUser('stream-setup@quran.test', 'stream_setup');

    $this->actingAs($user)->getJson('/api/v1/admin/streams')->assertStatus(200);

    $this->actingAs($user)
        ->postJson('/api/v1/admin/streams/'.Str::uuid().'/start')
        ->assertStatus(403);
});

test('losing a role takes the capability away on the next request', function (): void {
    // Enforcement reads through the cached effective set, so a stale
    // entry here would be a live authorization bug rather than a slow
    // page. The invalidation is the use case's, not the test's.
    $user = enforcementUser('revoked@quran.test', 'competition_manager');

    $this->actingAs($user)->getJson('/api/v1/admin/seasons')->assertStatus(200);

    revokeAllRoles((string) $user->id);

    $this->actingAs($user->fresh())->getJson('/api/v1/admin/seasons')->assertStatus(403);
});
