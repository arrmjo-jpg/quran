<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\RoleId;
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
 * PE-2 IS NOT ENFORCED HERE YET. "You cannot edit a role into something
 * you could not grant" requires the acting user's effective permissions,
 * which requires user↔role resolution — the next epic. Until then this
 * use case is reachable only by an admin, because the whole admin API
 * still sits behind EnsureUserIsAdmin. The PE-2 check belongs here and
 * lands with the epic that can answer the question it asks.
 *
 * CACHE INVALIDATION likewise: changing a role's permissions changes the
 * effective set of every user holding it, and those users' cached
 * permissions must be dropped. There is no cache yet; the invalidation
 * call belongs in this method when there is (ADR-015 §4.6).
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
            $permissions = CreateRoleUseCase::assertKnown(array_values(array_unique($permissionNames)));

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
}
