<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Permissions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * Resolves what a user may actually do — ADR-015 §4.6.
 *
 *     User → role_user → role_has_permissions → permissions
 *
 * One indexed join, cached per user. This is the read model the whole
 * identity epic was building toward; nothing above Infrastructure knows
 * how it is stored.
 *
 * THE CACHE IS NOT THE CORRECTNESS MECHANISM — invalidation is. The TTL
 * is a backstop against a key that somehow outlives its invalidation,
 * not the thing keeping authorization fresh. A stale permission set is a
 * security bug rather than a performance one, which is why the writers
 * are enumerated rather than trusted to remember.
 *
 * WHY THE WRITER LIST IS PROVABLY COMPLETE: ADR-015 forbids granting a
 * permission directly to a user, so `model_has_permissions` does not
 * exist and the only inputs to a user's effective set are the roles they
 * hold and the permissions those roles grant. That leaves exactly three
 * ways it can change, and each is a use case that calls this class:
 *
 *   1. the user's roles change        → forget(user)
 *   2. a role's permissions change    → forgetHoldersOf(role)
 *   3. a role is deleted              → holdersOf(role) BEFORE the
 *                                        delete, then forget each
 *
 * Creating a user with roles is a fourth write but needs no
 * invalidation: no key exists yet.
 *
 * Cache::flush() is never called here. Flushing would also drop every
 * unrelated cache the platform keeps, and would hide a missing
 * invalidation by making everything appear to work.
 */
final class EffectivePermissionResolver
{
    /**
     * A backstop, not the mechanism. Long on purpose: a short TTL would
     * paper over a missed invalidation and make the bug intermittent
     * instead of reproducible.
     */
    private const TTL_SECONDS = 86_400;

    /**
     * Every permission name the user effectively holds, via their roles.
     *
     * @return array<int, string>
     */
    public function forUser(UserId $userId): array
    {
        return Cache::remember(
            self::keyFor($userId),
            self::TTL_SECONDS,
            fn (): array => $this->resolve($userId)
        );
    }

    public function has(UserId $userId, string $permission): bool
    {
        return in_array($permission, $this->forUser($userId), true);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function hasAll(UserId $userId, array $permissions): bool
    {
        $held = $this->forUser($userId);

        foreach ($permissions as $permission) {
            if (! in_array($permission, $held, true)) {
                return false;
            }
        }

        return true;
    }

    public function forget(UserId $userId): void
    {
        Cache::forget(self::keyFor($userId));
    }

    /**
     * Drops the cached set of every user holding this role.
     *
     * Callers deleting a role must use holdersOf() first — see the note
     * there — because by the time the row is gone this method can no
     * longer find anyone.
     */
    public function forgetHoldersOf(RoleId $roleId): void
    {
        foreach ($this->holdersOf($roleId) as $userId) {
            $this->forget(new UserId($userId));
        }
    }

    /**
     * The ids of users currently holding this role.
     *
     * Exists so a role deletion can capture its holders BEFORE the pivot
     * rows disappear. Invalidating afterwards would find nobody and
     * leave every former holder authorized by a stale cache entry until
     * the TTL expired — the exact failure this design is most exposed to.
     *
     * @return array<int, string>
     */
    public function holdersOf(RoleId $roleId): array
    {
        return DB::table('role_user')
            ->where('role_id', $roleId->value)
            ->pluck('user_id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function resolve(UserId $userId): array
    {
        return DB::table('role_user')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_user.user_id', $userId->value)
            ->distinct()
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();
    }

    private static function keyFor(UserId $userId): string
    {
        return "rbac:user:{$userId->value}:permissions";
    }
}
