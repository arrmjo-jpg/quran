<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Exceptions\PrivilegeEscalationException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;

/**
 * Replaces a role's permission set — ADR-015 §4.5.
 *
 * Takes the intended final set, not a delta: a client that sent "add X"
 * and "remove Y" separately could interleave with another editor and
 * leave a set neither of them intended. The aggregate derives what
 * actually changed and records it as RolePermissionsChanged.
 *
 * SYSTEM ROLES ARE REFUSED, by the aggregate. Their permissions are
 * defined in RolesSeeder and change only when that code changes — there
 * is no path from an API or a UI to super_admin's grants.
 *
 * PE-2 IS ENFORCED HERE: an actor may only put into a role permissions
 * they hold themselves. Without it PE-1 is trivially bypassed — grant
 * yourself a role you are allowed to grant, then edit that role into
 * anything.
 */
final readonly class SyncRolePermissionsUseCase
{
    public function __construct(
        private RoleRepositoryContract $roles,
        private EffectivePermissionResolver $permissions,
    ) {}

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function execute(string $roleId, array $permissionNames, ?string $byUserId = null): Role
    {
        return DB::transaction(function () use ($roleId, $permissionNames, $byUserId): Role {
            $role = $this->roles->findOrFail(new RoleId($roleId));

            // Unknown names are rejected before the aggregate sees them:
            // the catalogue is the single source of truth, and a grant
            // nothing defines would be a capability nothing ever checks.
            $names = array_values(array_unique($permissionNames));
            $permissions = CreateRoleUseCase::assertKnown($names);

            // PE-2 — only permissions the actor holds. Checked against
            // what is being ADDED: removing a permission the actor does
            // not hold is not an escalation, and refusing it would stop
            // an administrator from trimming a role they inherited.
            $this->assertActorHolds(
                $byUserId,
                $role->getName(),
                array_values(array_diff($names, $role->getPermissionNames()))
            );

            $role->syncPermissions($permissions, $byUserId);

            $this->roles->save($role);

            // Writer 2 of 3 (ADR-015 §4.6): every user holding this role
            // now has a different effective set. The pivot is untouched
            // by this operation, so the holders are still resolvable
            // after the save.
            $this->permissions->forgetHoldersOf($role->id);

            foreach ($role->releaseEvents() as $event) {
                event($event);
            }

            return $role;
        });
    }

    /**
     * PE-2 — you cannot put into a role a permission you do not hold.
     *
     * A null actor is a system action (seeder, console command) and is
     * exempt, for the same reason as PE-1: that code is already trusted,
     * and the alternative would be making the seeder impersonate someone.
     *
     * @param  array<int, string>  $beingAdded
     */
    private function assertActorHolds(?string $byUserId, string $roleName, array $beingAdded): void
    {
        if ($byUserId === null || $beingAdded === []) {
            return;
        }

        $actorHolds = $this->permissions->forUser(new UserId($byUserId));
        $exceeds = array_values(array_diff($beingAdded, $actorHolds));

        if ($exceeds !== []) {
            throw new PrivilegeEscalationException($roleName, $exceeds);
        }
    }
}
