<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Exceptions\LastSystemRoleHolderException;
use Modules\Core\Domain\Events\UserDeactivated;
use Modules\Core\Domain\Exceptions\SelfDeactivationException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * Takes an account out of service.
 *
 * User::deactivate() existed from the first identity epic and nothing had
 * ever called it — the capability was on the aggregate and absent from the
 * system. Wiring it up opens a second door onto the lock PE-5 guards, which
 * is why both guards below are here rather than in whatever calls this.
 *
 * A deactivated account is allowed nothing, whatever roles it still holds
 * (AuthorizationService::permissionsOf). That is the point, and it is also
 * the danger: deactivating the last active holder of a system role ends
 * with nobody able to administer the platform and nobody able to reactivate
 * them, because reactivation is itself a permission. The recovery would be
 * an edit straight into the database.
 */
final class DeactivateUserUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
        private RoleRepositoryContract $roles,
    ) {}

    public function execute(string $userId, ?string $byUserId = null): User
    {
        return DB::transaction(function () use ($userId, $byUserId): User {
            // PE-6 — you cannot deactivate yourself. Checked before anything
            // is read about roles, because it holds regardless of them.
            if ($byUserId !== null && $byUserId === $userId) {
                throw new SelfDeactivationException($userId);
            }

            $user = $this->users->findOrFail(new UserId($userId));

            $this->assertSystemRoleHolderSurvives($user);

            // Nothing to record when the account is already out of service.
            // An audit entry saying "deactivated" for an account that was
            // already deactivated is noise in the log that matters most.
            if (! $user->isActive()) {
                return $user;
            }

            $user->deactivate();
            $this->users->save($user);

            // Emitted from the use case, as ADR-015 §4.5 specifies for this
            // pair: the aggregate does not know who acted, and byUserId is
            // the whole point of the entry.
            event(new UserDeactivated(
                userId: $userId,
                byUserId: $byUserId,
                occurredAt: now()->toIso8601String(),
            ));

            // No cache invalidation here on purpose. The effective set is
            // derived from roles and has not changed; what changed is
            // whether the account may act on it, which AuthorizationService
            // asks separately on every request. Forgetting the key would
            // suggest the two are the same question.
            return $user;
        });
    }

    /**
     * PE-5, reached through deactivation instead of through role removal.
     *
     * The same rule and the same exception as SyncUserRolesUseCase, and
     * deliberately not extracted into a shared helper: that one asks about
     * the roles being REMOVED and this one about every role the account
     * holds. The counts differ too — there, a role survives if anyone else
     * holds it; here, the account being deactivated is still counted as
     * active while the check runs, so "the last one" is `<= 1`.
     *
     * countUsersWithRole() counts only active, non-deleted accounts, which
     * is what makes this check meaningful at all: a deactivated holder is
     * not an administrator the platform can call on.
     */
    private function assertSystemRoleHolderSurvives(User $user): void
    {
        foreach ($user->getRoleIds() as $roleId) {
            $role = $this->roles->find($roleId);

            if ($role === null || ! $role->isSystem()) {
                continue;
            }

            if ($this->users->countUsersWithRole($role->id) <= 1) {
                throw new LastSystemRoleHolderException($role->getName());
            }
        }
    }
}
