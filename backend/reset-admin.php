<?php

use Illuminate\Contracts\Console\Kernel;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;

/*
|--------------------------------------------------------------------------
| Bootstrap the rescue account
|--------------------------------------------------------------------------
|
| This is the maintenance account, not a permissions test subject: it holds
| super_admin so that a development environment always has someone who can
| administer it. Accounts for exercising refusals live in seed-qa-accounts.php,
| because narrowing this one would leave an environment with no way back in.
|
| Before RBAC was switched on, creating the row was enough — `type = 'admin'`
| granted everything by itself. It no longer does, so an account created by the
| old version of this script could sign in and then be refused by every admin
| endpoint, which looks like a broken panel rather than a missing grant.
|
| Idempotent on every step, so it is safe to re-run after a migration, after a
| catalogue change, or on a database that is already correct:
|
|   1. PermissionsSeeder — inserts catalogue entries that are missing and
|      never deletes ones that are not (a removed permission may still be
|      referenced by a live role).
|   2. RolesSeeder — re-syncs system roles from the catalogue and leaves the
|      operator-owned roles untouched.
|   3. The account itself.
|   4. super_admin, only if it is not already held.
|
|   docker exec quran_platform_app php reset-admin.php
*/

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "Seeding the permission catalogue...\n";
(new PermissionsSeeder)->run();
app(RolesSeeder::class)->run();

$user = UserModel::query()->updateOrCreate(
    ['email' => 'admin@quran.test'],
    [
        'id' => '01920000-0000-7000-8000-000000000001',
        'name' => 'Platform Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
        'mfa_enabled' => false,
    ]
);

$users = app(UserRepositoryContract::class);
$roles = app(RoleRepositoryContract::class);

$superAdmin = $roles->findByName('super_admin');
$account = $users->findOrFail(new UserId((string) $user->id));

// Granted through the aggregate and its repository rather than through
// SyncUserRolesUseCase. That use case enforces PE-1 — an actor may not grant
// what they do not hold — and this script has no actor: it is the bootstrap
// path that exists precisely for when nobody can grant anything yet.
if (! $account->hasRole($superAdmin->id)) {
    $account->assignRole($superAdmin->id);
    $users->save($account);

    // The effective set is cached per user, and this one may have been read
    // already in this environment. Invalidation is the correctness mechanism.
    app(EffectivePermissionResolver::class)->forget(new UserId((string) $user->id));

    echo "Granted super_admin.\n";
} else {
    echo "super_admin already held.\n";
}

$held = app(EffectivePermissionResolver::class)->forUser(new UserId((string) $user->id));

echo 'Admin user reset successfully: '.$user->email."\n";
echo 'Password: Pass123!'."\n";
echo 'Effective permissions: '.count($held)."\n";
