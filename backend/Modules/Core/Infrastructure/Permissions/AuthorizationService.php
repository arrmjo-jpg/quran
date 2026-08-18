<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Permissions;

use Modules\Core\Domain\ValueObjects\PermissionName;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Infrastructure\Database\Models\UserModel;

/**
 * The one place that answers "may this account do this?" — ADR-015 §6.
 *
 * Sits between the resolver (which knows what a user holds) and Laravel's
 * Gate (which callers actually use). Keeping it separate from the
 * resolver matters because the answer is not simply "is the permission in
 * the set": an inactive account holds its roles but may do nothing, and
 * that rule belongs to authorization rather than to resolution.
 *
 * WIRED: every catalogue permission is registered as a Gate ability, and
 * every admin route carries a `can:` check that lands here. EnsureUserIsAdmin
 * still guards the surface and answers only "may this account reach the
 * admin API at all"; everything past that door is decided below.
 */
final class AuthorizationService
{
    public function __construct(
        private readonly EffectivePermissionResolver $permissions,
    ) {}

    /**
     * Whether the account may exercise this permission.
     *
     * An unknown permission name is refused rather than treated as
     * ungoverned. The alternative — unknown means allowed — turns a typo
     * in a Gate check into an open door, which is the wrong direction for
     * a mistake to fail in.
     */
    public function allows(UserModel $user, string $permission): bool
    {
        if (! $this->isEligible($user)) {
            return false;
        }

        if (! PermissionCatalog::has($permission)) {
            return false;
        }

        return $this->permissions->has(new UserId((string) $user->id), $permission);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function allowsAll(UserModel $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->allows($user, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function allowsAny(UserModel $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->allows($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Everything this account may currently do.
     *
     * Returns nothing for an ineligible account rather than the set it
     * would hold if reactivated — a UI reading this must not show a
     * deactivated admin the buttons they cannot use.
     *
     * @return array<int, string>
     */
    public function permissionsOf(UserModel $user): array
    {
        if (! $this->isEligible($user)) {
            return [];
        }

        return $this->permissions->forUser(new UserId((string) $user->id));
    }

    /**
     * The roles this account holds, by name.
     *
     * Unlike permissionsOf(), this does NOT empty for a deactivated
     * account: it still holds those roles, and a screen listing users
     * needs to say so. The difference is the point — roles describe the
     * account, permissions describe what it may do right now, and only
     * the second is safe to make a UI decision with.
     *
     * @return array<int, string>
     */
    public function rolesOf(UserModel $user): array
    {
        return $this->permissions->roleNamesFor(new UserId((string) $user->id));
    }

    /**
     * @return array<int, PermissionName>
     */
    public function permissionNamesOf(UserModel $user): array
    {
        return PermissionName::fromMany($this->permissionsOf($user));
    }

    /**
     * Deactivated and soft-deleted accounts may do nothing, whatever they
     * hold. This is why authorization is not a synonym for resolution:
     * the roles are still attached and still correct, but the account is
     * not permitted to act on them.
     *
     * `type` is deliberately NOT checked here. Which surface an account
     * may reach is the middleware's question (ADR-015 §1); conflating the
     * two would put the door check in two places that could disagree.
     */
    private function isEligible(UserModel $user): bool
    {
        return (bool) $user->is_active && $user->deleted_at === null;
    }
    /**
     * Role names for a page of accounts, keyed by user id.
     *
     * Like rolesOf(), this does NOT empty for deactivated accounts: the
     * roles are still held and a list that hid them would be describing
     * authorization rather than membership.
     *
     * @param  array<int, string>  $userIds
     * @return array<string, array<int, string>>
     */
    public function rolesOfMany(array $userIds): array
    {
        return $this->permissions->roleNamesForMany($userIds);
    }
}
