<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Core\Application\UseCases\AssignRoleToUserUseCase;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Stable on purpose. The repair migration re-keys the old invalid row to
     * exactly this id, so generating one here would leave the seeder and the
     * migration disagreeing about who the bootstrap admin is. Version 7 and
     * an RFC-4122 variant, which is what UserId accepts and what
     * HardcodedUuidTest asserts of every literal in the tree.
     */
    private const ADMIN_ID = '01920000-0000-7000-8000-000000000001';

    private const ADMIN_EMAIL = 'admin@quran.test';

    /**
     * Seed the application's database.
     */
    public function run(RoleRepositoryContract $roles, AssignRoleToUserUseCase $assignRole): void
    {
        // Reference data: idempotent, safe on every deploy (ADR-015 §4.3).
        $this->call(PermissionsSeeder::class);
        $this->call(RolesSeeder::class);

        $admin = $this->seedAdminAccount();

        $superAdmin = $roles->findByName('super_admin');

        if ($superAdmin === null) {
            throw new RuntimeException(
                'RolesSeeder produced no super_admin role. Seeding an admin account without it '
                .'leaves an environment whose administrator is refused by every admin endpoint.'
            );
        }

        // The sanctioned write path, with a null actor: PE-1 exempts system
        // actions by design — SyncUserRolesUseCase documents a seeder as the
        // case it exists for — so there is no reason to touch the pivot here.
        // Going through the use case is also what keeps the UserRolesChanged
        // event, the transaction and the effective-permission cache
        // invalidation from having to be remembered in this file.
        //
        // Idempotent by way of the use case, not by a check added here: it
        // returns early when the intended set matches the held one, so a
        // re-run fires no event and drops no cache.
        $assignRole->execute((string) $admin->id, $superAdmin->id->value, byUserId: null);
    }

    /**
     * The id is written when the row is created and never afterwards.
     *
     * Passing it inside updateOrCreate's update payload — as this seeder did —
     * rewrites the primary key of a row that already exists. Every foreign key
     * pointing at users declares ON UPDATE NO ACTION, so the database refuses
     * that the moment anything references the account, and `audit_logs.actor_id`
     * carries no constraint at all and would simply be orphaned.
     */
    private function seedAdminAccount(): UserModel
    {
        $admin = UserModel::query()->firstOrNew(['email' => self::ADMIN_EMAIL]);

        if (! $admin->exists) {
            $admin->id = self::ADMIN_ID;
        }

        $admin->fill([
            'name' => 'Platform Admin',
            'type' => 'admin',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ])->save();

        return $admin;
    }
}
