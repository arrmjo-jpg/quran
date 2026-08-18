<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Events\UserRolesChanged;
use Modules\Core\Domain\Exceptions\LastSystemRoleHolderException;
use Modules\Core\Domain\Exceptions\PrivilegeEscalationException;
use Modules\Core\Domain\Exceptions\SelfRoleChangeException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;
use RuntimeException;

/**
 * Replaces the set of roles a user holds — ADR-015 §4.5.
 *
 * This is the single write path. AssignRoleToUser and RevokeRoleFromUser
 * are conveniences over it rather than separate mechanisms, so every
 * change goes through the same guards and emits the same event; there is
 * no cheaper door into the pivot.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO: answer "what may this user do".
 * Turning roles into an effective permission set — and caching it, and
 * wiring it to Gate — is the enforcement epic. Until then this use case
 * establishes only that a user holds roles.
 *
 * PE-1 IS ENFORCED HERE: an actor may only assign a role whose
 * permissions are a subset of their own effective set. Without it,
 * anyone able to edit users could assign themselves — or a confederate —
 * a role far beyond their own reach, which would make every other guard
 * in this design decorative.
 */
final readonly class SyncUserRolesUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
        private RoleRepositoryContract $roles,
        private EffectivePermissionResolver $permissions,
    ) {}

    /**
     * @param  array<int, string>  $roleIds  the intended final set
     */
    public function execute(string $userId, array $roleIds, ?string $byUserId = null): User
    {
        return DB::transaction(function () use ($userId, $roleIds, $byUserId): User {
            // PE-3 — an account may not change its own roles, whatever it
            // holds. Checked before anything is read, so the refusal does
            // not depend on the target existing.
            if ($byUserId !== null && $byUserId === $userId) {
                throw new SelfRoleChangeException($userId);
            }

            $user = $this->users->findOrFail(new UserId($userId));

            $desired = $this->resolveRoles(array_values(array_unique($roleIds)));

            $before = $user->getRoleIdValues();
            $after = array_keys($desired);

            $addedIds = array_values(array_diff($after, $before));
            $removedIds = array_values(array_diff($before, $after));

            if ($addedIds === [] && $removedIds === []) {
                return $user;
            }

            // PE-1 — only roles the actor could grant. Checked against
            // the roles being ADDED: removing a role the actor does not
            // themselves hold is not an escalation, and refusing it would
            // stop an administrator from cleaning up after someone more
            // privileged had left.
            $this->assertActorCanGrant($byUserId, array_map(
                static fn (string $id): Role => $desired[$id],
                $addedIds
            ));

            $this->assertSystemRoleHolderSurvives($removedIds);

            $user->syncRoles(array_map(static fn (string $id): RoleId => new RoleId($id), $after));

            $this->users->save($user);

            // Writer 1 of 3 (ADR-015 §4.6): this user's effective set
            // just changed, so their cached copy must go.
            $this->permissions->forget($user->id);

            // Names, not ids: an audit entry reading "granted 0192…" is
            // unreadable, and the role may be renamed or gone by the time
            // anyone reads it. Removed roles are resolved from the set the
            // user held, which is still loadable at this point.
            event(new UserRolesChanged(
                userId: $userId,
                added: $this->namesOf($addedIds),
                removed: $this->namesOf($removedIds),
                byUserId: $byUserId,
                occurredAt: now()->toIso8601String(),
            ));

            return $user;
        });
    }

    /**
     * PE-1 — you cannot grant what you do not hold.
     *
     * The actor's own effective permissions must be a superset of every
     * permission the roles being granted carry. Expressed entirely in
     * permissions: no role name, no ranking, no "is this actor senior
     * enough" shortcut. A hierarchy would need an ordering nothing in
     * this design defines, and would go stale the moment a role's
     * contents changed.
     *
     * A null actor is a system action — a seeder or a console command —
     * and is not subject to PE-1. Those run from code that is already
     * trusted, and the alternative would be requiring the seeder to
     * impersonate someone.
     *
     * @param  array<int, Role>  $rolesBeingGranted
     */
    private function assertActorCanGrant(?string $byUserId, array $rolesBeingGranted): void
    {
        if ($byUserId === null || $rolesBeingGranted === []) {
            return;
        }

        $actorHolds = $this->permissions->forUser(new UserId($byUserId));

        foreach ($rolesBeingGranted as $role) {
            $exceeds = array_values(array_diff($role->getPermissionNames(), $actorHolds));

            if ($exceeds !== []) {
                throw new PrivilegeEscalationException($role->getName(), $exceeds);
            }
        }
    }

    /**
     * PE-5 — the last active account holding a SYSTEM role keeps it.
     *
     * Expressed through `roles.is_system` rather than by naming
     * super_admin. Naming it would put that string in one more place
     * that has to stay correct, and a second system role added later
     * would fall silently outside the protection — the same reasoning
     * that made PE-4 a column instead of a hardcoded list.
     *
     * Only the roles actually being removed are checked, so an unrelated
     * change never pays for the count query.
     *
     * @param  array<int, string>  $removedIds
     */
    private function assertSystemRoleHolderSurvives(array $removedIds): void
    {
        foreach ($removedIds as $roleId) {
            $role = $this->roles->find(new RoleId($roleId));

            if ($role === null || ! $role->isSystem()) {
                continue;
            }

            if ($this->users->countUsersWithRole($role->id) <= 1) {
                throw new LastSystemRoleHolderException($role->getName());
            }
        }
    }

    /**
     * @param  array<int, string>  $roleIds
     * @return array<string, Role>
     */
    private function resolveRoles(array $roleIds): array
    {
        $resolved = [];

        foreach ($roleIds as $roleId) {
            $role = $this->roles->find(new RoleId($roleId));

            if ($role === null) {
                throw new RuntimeException("Cannot assign a role that does not exist: {$roleId}");
            }

            $resolved[$roleId] = $role;
        }

        return $resolved;
    }

    /**
     * @param  array<int, string>  $roleIds
     * @return array<int, string>
     */
    private function namesOf(array $roleIds): array
    {
        return array_values(array_filter(array_map(
            fn (string $id): ?string => $this->roles->find(new RoleId($id))?->getName(),
            $roleIds
        )));
    }
}
