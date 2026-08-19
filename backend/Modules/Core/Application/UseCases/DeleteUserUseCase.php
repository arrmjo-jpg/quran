<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Events\UserDeleted;
use Modules\Core\Domain\Exceptions\LastSystemRoleHolderException;
use Modules\Core\Domain\Exceptions\SelfDeactivationException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * Takes an account out of service permanently as far as the platform is
 * concerned — but never destroys it (ADR-016 D5).
 *
 * THIS OPENS A DOOR ADR-016 RECORDED AS "CLOSED BY ABSENCE". The amendment to
 * PE-5 said deletion had no endpoint, so that third route to stranding the
 * platform was shut simply because nothing could reach it. Building the
 * endpoint reopens it, so both guards arrive in the same commit rather than
 * being noticed later:
 *
 *   PE-6 — you cannot delete yourself.
 *   PE-5 — the last active holder of a system role cannot lose it, and
 *          deleting them takes it away as surely as revoking it does.
 *
 * Deliberately NOT sharing a helper with DeactivateUserUseCase, for the reason
 * recorded there: the rule reads the same and the question differs. That one
 * asks about an account that will still exist; this one about an account that
 * will not be findable at all afterwards.
 */
final class DeleteUserUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
        private RoleRepositoryContract $roles,
    ) {}

    public function execute(string $userId, ?string $byUserId = null): User
    {
        return DB::transaction(function () use ($userId, $byUserId): User {
            // PE-6, checked first because it holds regardless of roles. Reuses
            // SelfDeactivationException: the rule is one rule — "you cannot
            // remove your own access" — and inventing a second exception for
            // the second door would suggest they were different decisions.
            if ($byUserId !== null && $byUserId === $userId) {
                throw new SelfDeactivationException($userId);
            }

            $user = $this->users->findOrFail(new UserId($userId));

            $this->assertSystemRoleHolderSurvives($user);

            $this->users->delete($user->id);

            event(new UserDeleted(
                userId: $userId,
                byUserId: $byUserId,
                occurredAt: now()->toIso8601String(),
            ));

            return $user;
        });
    }

    /**
     * countUsersWithRole() counts only active, non-deleted accounts, which is
     * what makes this meaningful: a deleted holder cannot administer anything,
     * so it must not be what keeps the guarantee satisfied.
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
