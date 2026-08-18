<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;

/*
|--------------------------------------------------------------------------
| Accounts for exercising refusals by hand
|--------------------------------------------------------------------------
|
| The automated suite proves that a permission is refused. It cannot show that
| a button is hidden, that a 403 reaches the operator as a readable message, or
| that a screen still makes sense once half its controls are gone. That needs a
| person, signed in as somebody who is genuinely not allowed.
|
| Which is why these are separate accounts rather than a narrowed rescue
| account: reset-admin.php exists so an environment always has a way back in,
| and taking its permissions away to test refusals is how an environment ends
| up with nobody able to fix it.
|
| Every account is created deactivate-safe and idempotent — re-running this
| re-syncs the roles and changes nothing else.
|
|   docker exec quran_platform_app php seed-qa-accounts.php
|
| Password for all of them: Pass123!
*/

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

/*
 * Chosen so each one answers a different question in the panel:
 *
 *   manager    runs the competition and holds NO identity capability at all,
 *              so /users and /roles must be absent from its sidebar entirely
 *              — this is ADR-015 §7.3's separation, seen rather than asserted.
 *   moderator  a narrow operator role; most action buttons should be gone.
 *   judge      the /judge surface only; the admin panel should be a wall of
 *              refusals, which is the case the type gate alone used to allow.
 *   inactive   holds super_admin and is deactivated, so it proves that a
 *              deactivated account is allowed nothing WHATEVER it holds —
 *              the distinction between resolution and authorization, live.
 */
$accounts = [
    ['email' => 'manager@quran.test',   'name' => 'QA Competition Manager', 'role' => 'competition_manager', 'active' => true],
    ['email' => 'moderator@quran.test', 'name' => 'QA Moderator',           'role' => 'moderator',           'active' => true],
    ['email' => 'judge@quran.test',     'name' => 'QA Judge',               'role' => 'judge',               'active' => true],
    ['email' => 'inactive@quran.test',  'name' => 'QA Deactivated Admin',   'role' => 'super_admin',         'active' => false],
];

$users = app(UserRepositoryContract::class);
$roles = app(RoleRepositoryContract::class);
$resolver = app(EffectivePermissionResolver::class);

if ($roles->findByName('super_admin') === null) {
    echo "Roles are not seeded. Run reset-admin.php first.\n";
    exit(1);
}

foreach ($accounts as $spec) {
    $existing = UserModel::query()->firstWhere('email', $spec['email']);

    $model = UserModel::query()->updateOrCreate(
        ['email' => $spec['email']],
        [
            // UserModel generates nothing: ids come from the domain
            // repository, and this script writes through Eloquent. Supplied
            // only when the row is new — rewriting the id of an existing
            // account is the operation ten foreign keys refuse, and the
            // reason the bootstrap admin needed a migration rather than an
            // UPDATE. Str::uuid() is v4, which the value objects accept.
            'id' => $existing?->id ?? (string) Str::uuid(),
            'name' => $spec['name'],
            'type' => 'admin',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => $spec['active'],
            'mfa_enabled' => false,
        ]
    );

    $role = $roles->findByName($spec['role']);
    $account = $users->findOrFail(new UserId((string) $model->id));

    // syncRoles rather than assignRole, so re-running fixes an account whose
    // roles were changed by hand during a previous QA session.
    $account->syncRoles([$role->id]);
    $users->save($account);
    $resolver->forget(new UserId((string) $model->id));

    printf(
        "%-24s role=%-20s active=%s permissions=%d\n",
        $spec['email'],
        $spec['role'],
        $spec['active'] ? 'yes' : 'NO',
        count($resolver->forUser(new UserId((string) $model->id)))
    );
}

echo "\nPassword for all: Pass123!\n";
